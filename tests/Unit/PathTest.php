<?php

namespace Novay\BunnySecret\Tests\Unit;

use Novay\BunnySecret\Support\Path;
use PHPUnit\Framework\TestCase;

class PathTest extends TestCase
{
    public function test_it_cleans_paths(): void
    {
        $this->assertSame('foo/bar.jpg', Path::clean('/foo//bar.jpg'));
        $this->assertSame('foo/bar.jpg', Path::clean('foo\\bar.jpg'));
    }

    public function test_it_joins_paths(): void
    {
        $this->assertSame('foo/bar/baz.jpg', Path::join('/foo/', '/bar/', 'baz.jpg'));
    }

    public function test_it_extracts_path_from_url(): void
    {
        $this->assertSame('images/photo.jpg', Path::fromUrl('https://cdn.example.com/images/photo.jpg'));
    }
}
