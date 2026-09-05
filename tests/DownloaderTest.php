<?php

namespace Project\tests;

use PHPUnit\Framework\TestCase;
use Spatie\TemporaryDirectory\TemporaryDirectory;

use function Downloader\Downloader\downloadPage;
use function Project\tests\Utils\{getFixturePath, joinPath};

class DownloaderTest extends TestCase
{
    private const PAGE_URL = 'http://site.com/blog/about';
    private const PAGE_FILENAME = 'site-com-blog-about.html';
    private const ASSETS_DIRNAME = 'site-com-blog-about_files';

    /** @var array<int, string> */
    private const ASSET_FILENAMES = [
        'site-com-blog-about-assets-styles.css',
        'site-com-photos-me.jpg',
        'site-com-assets-scripts.js',
        'site-com-blog-about.html',
    ];

    private TemporaryDirectory $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = (new TemporaryDirectory())->create();
    }

    protected function tearDown(): void
    {
        $this->outputDir->delete();
    }

    private function outputPath(string ...$parts): string
    {
        return joinPath((string) realpath($this->outputDir->path()), ...$parts);
    }

    private function download(?string $url = null, ?string $outputDir = null): string
    {
        return downloadPage(
            $url ?? self::PAGE_URL,
            $outputDir ?? $this->outputDir->path(),
            FakeClient::class,
        );
    }

    public function testReturnsPathOfSavedPage(): void
    {
        $filepath = $this->download();

        $this->assertSame($this->outputPath(self::PAGE_FILENAME), $filepath);
        $this->assertFileExists($filepath);
    }

    public function testLocalLinksAreReplacedAndExternalOnesAreKept(): void
    {
        $this->download();

        $this->assertStringEqualsFile(
            $this->outputPath(self::PAGE_FILENAME),
            (string) file_get_contents(getFixturePath('expected', self::PAGE_FILENAME)),
        );
    }

    /**
     * @dataProvider assetFilenamesProvider
     */
    public function testAssetIsSavedWithItsRealContent(string $assetFilename): void
    {
        $this->download();

        $actualPath = $this->outputPath(self::ASSETS_DIRNAME, $assetFilename);
        $expectedPath = getFixturePath('expected', self::ASSETS_DIRNAME, $assetFilename);

        $this->assertFileExists($actualPath);
        $this->assertStringEqualsFile($actualPath, (string) file_get_contents($expectedPath));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function assetFilenamesProvider(): array
    {
        $cases = [];

        foreach (self::ASSET_FILENAMES as $filename) {
            $cases[$filename] = [$filename];
        }

        return $cases;
    }

    public function testAssetsDirectoryHoldsOnlyLocalResources(): void
    {
        $this->download();

        $saved = array_values(array_diff(
            (array) scandir($this->outputPath(self::ASSETS_DIRNAME)),
            ['.', '..'],
        ));
        sort($saved);

        $expected = self::ASSET_FILENAMES;
        sort($expected);

        $this->assertSame($expected, $saved);
    }

    public function testHostIsUnreachable(): void
    {
        $this->expectExceptionMessage('Could not resolve host');

        $this->download('https://badsite.com');
    }

    public function testServerAnswersWithInternalError(): void
    {
        $this->expectExceptionMessageMatches('/500 Internal Server Error/');

        $this->download('http://site.com/500');
    }

    public function testServerAnswersWithNotFound(): void
    {
        $this->expectExceptionMessageMatches('/404 Not Found/');

        $this->download('http://site.com/404');
    }

    public function testNothingIsWrittenWhenThePageCannotBeFetched(): void
    {
        try {
            $this->download('http://site.com/404');
        } catch (\Exception) {
            // ошибка проверяется отдельным тестом, здесь важен диск
        }

        $this->assertFileDoesNotExist($this->outputPath(self::PAGE_FILENAME));
        $this->assertDirectoryDoesNotExist($this->outputPath(self::ASSETS_DIRNAME));
    }

    public function testOutputDirectoryDoesNotExist(): void
    {
        $missingDir = $this->outputPath('there-is-no-such-directory');

        $this->expectException(\Exception::class);

        $this->download(null, $missingDir);
    }

    public function testOutputPathIsAFileAndNotADirectory(): void
    {
        $this->expectException(\Exception::class);

        $this->download(null, getFixturePath(self::PAGE_FILENAME));
    }

    public function testOutputDirectoryIsNotWritable(): void
    {
        $this->expectException(\Exception::class);

        $this->download(null, '/sys');
    }

    public function testAssetsDirectoryAlreadyExists(): void
    {
        mkdir($this->outputPath(self::ASSETS_DIRNAME));

        $this->expectException(\Exception::class);

        $this->download();
    }
}
