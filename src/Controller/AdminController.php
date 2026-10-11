<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Exception\HttpException;
use FileDrop\Http\Request;
use FileDrop\Http\Response;
use FileDrop\Repository\{FileRepository, IpBlockRepository, LimitEventRepository, StatsRepository, UserRepository};
use FileDrop\Service\FileService;
use FileDrop\Support\{AdminSession, Config, IpRange, Permissions, View};

final class AdminController extends PanelController
{
    private const PER_PAGE = 20;
    private const CHART_DAYS = 30;
    private const LIMIT_EVENTS_HOURS = 24;
    private const BLOCK_DURATIONS = [3600, 86400, 604800, 2592000, 0];
    private const CLEANUP_RETURN = ['overview' => '/admin', 'files' => '/admin/files'];

    public function __construct(
        FileService $files,
        Config $config,
        AdminSession $session,
        View $view,
        UserRepository $users,
        private readonly StatsRepository $stats,
        private readonly IpBlockRepository $blocks,
        private readonly LimitEventRepository $events,
    ) {
        parent::__construct($files, $config, $session, $view, $users);
    }

    public function overview(Request $request): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        if (!$this->user->can(Permissions::STATS_VIEW)) {
            return Response::redirect($this->homeUrl());
        }

        $now = time();
        $disk = $this->files->storageUsage();
        $totals = $this->stats->fileTotals($now);

        return $this->page('admin/overview', 'Panoramica', 'overview', [
            'now' => $now,
            'disk' => $disk,
            'usage' => $this->usage($disk, $totals),
            'totals' => $totals,
            'downloads' => $this->stats->downloadTotals($now),
            'perDay' => $this->stats->downloadsPerDay(self::CHART_DAYS, $now),
            'top' => $this->stats->topFiles($now - self::CHART_DAYS * 86400, 5),
            'recent' => $this->stats->recentDownloads(10),
            'canCleanup' => $this->user->can(Permissions::FILES_DELETE),
        ]);
    }

    public function files(Request $request): Response
    {
        if (($denied = $this->guard($request, Permissions::FILES_VIEW)) !== null) {
            return $denied;
        }

        $query = $this->text($request->query['q'] ?? null, 100);
        $sort = $this->sortKey($request->query['sort'] ?? null);
        $dir = $request->query['dir'] ?? null;
        $ascending = $dir === 'asc' || ($dir !== 'desc' && $sort === 'name');
        $page = $this->pageNumber($request->query['page'] ?? null);
        $now = time();
        $list = $this->files->adminList($query, $sort, $ascending, $page, self::PER_PAGE);

        return $this->page('admin/files', 'File', 'files', [
            'now' => $now,
            'totals' => $this->stats->fileTotals($now),
            'list' => $list,
            'q' => $query,
            'sort' => $sort,
            'ascending' => $ascending,
            'retentionDays' => FileService::LOG_RETENTION_DAYS,
            'canDelete' => $this->user->can(Permissions::FILES_DELETE),
        ]);
    }

    public function security(Request $request): Response
    {
        if (($denied = $this->guard($request, Permissions::SECURITY_VIEW)) !== null) {
            return $denied;
        }

        $now = time();
        $blocks = $this->blocks->all($now);

        return $this->page('admin/security', 'Sicurezza', 'security', [
            'blocks' => $blocks,
            'blockedRanges' => array_column($blocks, 'cidr'),
            'limitEvents' => $this->events->recent($now - self::LIMIT_EVENTS_HOURS * 3600, 15),
            'limitHours' => self::LIMIT_EVENTS_HOURS,
            'ownIp' => $request->ip(),
            'canManage' => $this->user->can(Permissions::SECURITY_MANAGE),
        ]);
    }

    public function delete(Request $request, array $params): Response
    {
        if (($denied = $this->guard($request, Permissions::FILES_DELETE)) !== null) {
            return $denied;
        }
        $back = Response::redirect($this->listUrl($request->post));
        if (!$this->validPost($request)) {
            return $back;
        }

        try {
            $file = $this->files->adminDelete($params['code']);
            $this->session->flash('ok', sprintf('File «%s» eliminato.', $file->originalName));
        } catch (HttpException $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return $back;
    }

    public function cleanup(Request $request): Response
    {
        if (($denied = $this->guard($request, Permissions::FILES_DELETE)) !== null) {
            return $denied;
        }
        $target = self::CLEANUP_RETURN[$request->post['return'] ?? ''] ?? self::CLEANUP_RETURN['files'];
        if ($this->validPost($request)) {
            $removed = $this->files->purgeExpired();
            $this->session->flash('ok', match ($removed) {
                0 => 'Nessun file da eliminare.',
                1 => 'Eliminato 1 file scaduto o esaurito.',
                default => sprintf('Eliminati %d file scaduti o esauriti.', $removed),
            });
        }

        return Response::redirect($target === '/admin/files' ? $this->listUrl($request->post) : $target);
    }

    public function block(Request $request): Response
    {
        if (($denied = $this->guard($request, Permissions::SECURITY_MANAGE)) !== null) {
            return $denied;
        }
        $back = Response::redirect('/admin/security');
        if (!$this->validPost($request)) {
            return $back;
        }

        $cidr = IpRange::normalize($this->text($request->post['ip'] ?? null, 64));
        if ($cidr === null) {
            $this->session->flash('error', sprintf(
                'Indirizzo non valido. Accetto IPv4 e IPv6, singoli o in formato CIDR (non oltre /%d per IPv4 e /%d per IPv6).',
                IpRange::MIN_BITS_V4,
                IpRange::MIN_BITS_V6
            ));

            return $back;
        }
        if (IpRange::matches($request->ip(), [$cidr])) {
            $this->session->flash('error', 'Questo intervallo comprende il tuo indirizzo: non puoi bloccarti da solo.');

            return $back;
        }

        $seconds = (int) ($request->post['duration'] ?? 0);
        $seconds = in_array($seconds, self::BLOCK_DURATIONS, true) ? $seconds : 86400;
        $now = time();
        $this->blocks->add($cidr, $this->text($request->post['reason'] ?? null, 120), $now, $seconds === 0 ? null : $now + $seconds);
        error_log(sprintf('[admin-block] blocked cidr=%s seconds=%d by=%s user=%s', $cidr, $seconds, $request->ip(), $this->user->username));
        $this->session->flash('ok', sprintf('Bloccato %s.', $cidr));

        return $back;
    }

    public function unblock(Request $request): Response
    {
        if (($denied = $this->guard($request, Permissions::SECURITY_MANAGE)) !== null) {
            return $denied;
        }
        $back = Response::redirect('/admin/security');
        if (!$this->validPost($request)) {
            return $back;
        }

        $id = $request->post['id'] ?? null;
        if (is_string($id) && ctype_digit($id) && strlen($id) < 10 && $this->blocks->remove((int) $id)) {
            error_log(sprintf('[admin-block] unblocked id=%s by=%s user=%s', $id, $request->ip(), $this->user->username));
            $this->session->flash('ok', 'Blocco rimosso.');
        } else {
            $this->session->flash('error', 'Blocco non trovato.');
        }

        return $back;
    }

    private function usage(array $disk, array $totals): array
    {
        $active = max(0, $totals['bytes'] - $totals['stale_bytes']);
        $stale = $totals['stale_bytes'];
        $other = max(0, $disk['bytes'] - $totals['bytes']);

        return [
            'active' => $active,
            'stale' => $stale,
            'other' => $other,
            'sum' => $active + $stale + $other,
        ];
    }

    private function listUrl(array $source): string
    {
        $params = [];
        $query = $this->text($source['q'] ?? null, 100);
        if ($query !== '') {
            $params['q'] = $query;
        }
        if (is_string($source['sort'] ?? null) && isset(FileRepository::SORTS[$source['sort']])) {
            $params['sort'] = $source['sort'];
        }
        if (in_array($source['dir'] ?? null, ['asc', 'desc'], true)) {
            $params['dir'] = $source['dir'];
        }
        $page = $this->pageNumber($source['page'] ?? null);
        if ($page > 1) {
            $params['page'] = $page;
        }

        return '/admin/files' . ($params === [] ? '' : '?' . http_build_query($params));
    }

    private function sortKey(mixed $value): string
    {
        return is_string($value) && isset(FileRepository::SORTS[$value]) ? $value : 'created';
    }

    private function pageNumber(mixed $value): int
    {
        return is_string($value) && ctype_digit($value) && strlen($value) < 9 ? max(1, (int) $value) : 1;
    }
}