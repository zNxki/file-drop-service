<section class="notice">
    <p class="notice-status"><?= (int) $status ?></p>
    <h1><?= $this->e($message) ?></h1>
    <a class="btn" href="<?= $this->e($this->url()) ?>"><?= $this->t('error.home') ?></a>
</section>