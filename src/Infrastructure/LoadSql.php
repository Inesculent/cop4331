<?php

namespace App\Infrastructure;
use RuntimeException;

class LoadSql
{
    private string $root;
    private array $cache = [];


    public function __construct(string $sqlRoot)
    {
        $this->root = rtrim($sqlRoot, '/');
    }

    public function load(string $rPath)
    {
        if (!isset($this->cache[$rPath])) {
            // Construct the path
            $path = $this->root . '/' . ltrim($rPath, '/');
            if (!is_file($path)) {
                throw new RuntimeException("File '{$path}' not found");
            }
            $this->cache[$rPath] = trim(file_get_contents($path));
        }
        return $this->cache[$rPath];
    }
}
