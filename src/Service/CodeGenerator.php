<?php
declare(strict_types=1);

namespace FileDrop\Service;

final class CodeGenerator
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';

    public function generate(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';
        for($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }
        
        return $code;
    }
}
