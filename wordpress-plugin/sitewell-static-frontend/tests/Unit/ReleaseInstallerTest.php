<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sitewell\StaticFrontend\Admin\SettingsPage;
use Sitewell\StaticFrontend\ReleaseInstaller;
use ZipArchive;

final class ReleaseInstallerTest extends TestCase
{

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/sitewell-plugin-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
        $GLOBALS['sitewell_test_options'] = [];
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_it_verifies_extracts_and_activates_a_release(): void
    {
        $archivePath = $this->archive(
            [
            'index.html'      => '<h1>Sitewell release</h1>',
            'assets/site.css' => 'body { color: teal; }',
            ]
        );
        $releaseId   = 'wsr_abcdefghijklmnopqrstuvwxyz12';

        ( new ReleaseInstaller($this->directory . '/releases') )->install(
            [
            'release_id' => $releaseId,
            'checksum'   => hash_file('sha256', $archivePath),
            'size'       => filesize($archivePath),
            ],
            $archivePath,
        );

        $active = $GLOBALS['sitewell_test_options'][ SettingsPage::OPTION_ACTIVE_RELEASE ];
        self::assertSame($releaseId, $active['release_id']);
        self::assertSame('<h1>Sitewell release</h1>', file_get_contents($active['path'] . '/index.html'));
        self::assertSame('body { color: teal; }', file_get_contents($active['path'] . '/assets/site.css'));
    }

    public function test_it_rejects_a_release_whose_checksum_does_not_match(): void
    {
        $archivePath = $this->archive([ 'index.html' => 'Hello' ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed verification');

        ( new ReleaseInstaller($this->directory . '/releases') )->install(
            [
            'release_id' => 'wsr_abcdefghijklmnopqrstuvwxyz12',
            'checksum'   => str_repeat('0', 64),
            'size'       => filesize($archivePath),
            ],
            $archivePath,
        );
    }

    public function test_it_rejects_executable_files(): void
    {
        $archivePath = $this->archive(
            [
            'index.html'   => 'Hello',
            'backdoor.php' => '<?php echo "unsafe";',
            ]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsafe file path');

        ( new ReleaseInstaller($this->directory . '/releases') )->install(
            [
            'release_id' => 'wsr_abcdefghijklmnopqrstuvwxyz12',
            'checksum'   => hash_file('sha256', $archivePath),
            'size'       => filesize($archivePath),
            ],
            $archivePath,
        );
    }

    /**
     * @param array<string, string> $files 
     */
    private function archive( array $files ): string
    {
        $path    = $this->directory . '/release-' . count(glob($this->directory . '/*.zip')) . '.zip';
        $archive = new ZipArchive();
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ( $files as $name => $contents ) {
            $archive->addFromString($name, $contents);
        }

        $archive->close();

        return $path;
    }

    private function removeDirectory( string $path ): void
    {
        if (! is_dir($path) ) {
            return;
        }

        foreach ( array_diff(scandir($path) ?: [], [ '.', '..' ]) as $item ) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($itemPath) ? $this->removeDirectory($itemPath) : unlink($itemPath);
        }

        rmdir($path);
    }
}
