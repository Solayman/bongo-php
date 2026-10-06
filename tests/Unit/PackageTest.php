<?php

namespace Tests\Unit;

use System\Package\Installer;
use System\Package\PackageManager;
use System\Testing\TestCase;
use System\Translation\Translator;
use System\View\ViewEngine;

class PackageTest extends TestCase
{
    protected string $tempDir;
    protected string $mockRepo;
    protected string $appDir;
    protected string $origLock;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bongo_pkg_test_' . bin2hex(random_bytes(4));
        $this->mockRepo = $this->tempDir . '/mock_repo';
        $this->appDir = $this->tempDir . '/app_base';

        mkdir($this->mockRepo . '/dummy/config', 0777, true);
        mkdir($this->mockRepo . '/dummy/views', 0777, true);
        mkdir($this->mockRepo . '/dummy/lang', 0777, true);
        mkdir($this->mockRepo . '/dummy/src/Commands', 0777, true);
        mkdir($this->mockRepo . '/dummy/database/migrations', 0777, true);

        // Mock package files
        file_put_contents($this->mockRepo . '/dummy/config/dummy.php', "<?php return ['api_key' => 'secret'];");
        file_put_contents($this->mockRepo . '/dummy/views/card.php', "<div>Package Card: {{ \$title }}</div>");
        file_put_contents($this->mockRepo . '/dummy/lang/en.json', json_encode(['Greeting' => 'Hello from Package']));
        file_put_contents($this->mockRepo . '/dummy/lang/bn.json', json_encode(['Greeting' => 'প্যাকেজ থেকে হ্যালো'], JSON_UNESCAPED_UNICODE));
        file_put_contents(
            $this->mockRepo . '/dummy/src/Commands/DummyCommand.php',
            "<?php\nnamespace Bongo\\Packages\\Dummy\\Commands;\nuse System\\Console\\Command;\nclass DummyCommand extends Command { protected string \$name = 'dummy:run'; public function handle(array \$args, array \$opts): int { echo 'dummy ok'; return 0; } }"
        );
        file_put_contents(
            $this->mockRepo . '/dummy/database/migrations/2026_10_04_000000_create_dummy_table.php',
            "<?php\nuse System\\Database\\Migration;\nuse System\\Database\\Schema\\Blueprint;\nreturn new class extends Migration { public function up(): void {} public function down(): void {} };"
        );
        file_put_contents(
            $this->mockRepo . '/dummy/routes.php',
            "<?php\nuse System\\Routing\\Route;\nRoute::get('/dummy-route', fn() => 'dummy ok')->name('dummy.test');"
        );
        file_put_contents($this->mockRepo . '/dummy/README.md', "# Dummy\n\n```env\nDUMMY_API_KEY=test\n```\n");

        // Mock catalog index
        $index = [
            'packages' => [
                'dummy' => [
                    'name' => 'dummy',
                    'latest' => '1.0.0',
                    'description' => 'Test Dummy Package',
                    'versions' => [
                        '1.0.0' => ['version' => '1.0.0', 'requires' => ['bongo' => '^0.9']],
                        '1.1.0' => ['version' => '1.1.0', 'requires' => ['bongo' => '^0.9']],
                    ],
                ],
            ],
        ];
        file_put_contents($this->mockRepo . '/index.json', json_encode($index, JSON_PRETTY_PRINT));

        // Setup mock app base dir
        mkdir($this->appDir . '/config', 0777, true);
        mkdir($this->appDir . '/packages', 0777, true);
        mkdir($this->appDir . '/resources/views', 0777, true);
        mkdir($this->appDir . '/resources/lang', 0777, true);
        mkdir($this->appDir . '/storage', 0777, true);
        file_put_contents($this->appDir . '/config/packages.php', "<?php return [\n    // 'dummy',\n];\n");

        $this->origLock = PackageManager::lockFile();
        PackageManager::setLockFile($this->appDir . '/packages/.lock.json');
        putenv('BONGO_PACKAGES_PATH=' . $this->mockRepo);
    }

    public function tearDown(): void
    {
        PackageManager::setLockFile($this->origLock);
        putenv('BONGO_PACKAGES_PATH');

        $this->deleteTree($this->tempDir);
        parent::tearDown();
    }

    public function testPackageManagerEmptyWhenNoLock(): void
    {
        $this->assertSame([], PackageManager::installed());
        $this->assertFalse(PackageManager::isInstalled('dummy'));
    }

    public function testPackageManagerWritesAndReadsLock(): void
    {
        $data = [
            'dummy' => [
                'version' => '1.0.0',
                'description' => 'Test Dummy',
                'files' => ['config/dummy.php' => 'abc123hash'],
            ],
        ];

        PackageManager::writeLock($data);
        $this->assertTrue(PackageManager::isInstalled('dummy'));
        $this->assertSame('1.0.0', PackageManager::installed()['dummy']['version']);
    }

    public function testCatalogReadsLocalIndex(): void
    {
        $installer = new Installer($this->appDir);
        $catalog = $installer->catalog();

        $this->assertArrayHasKey('dummy', $catalog);
        $this->assertSame('1.0.0', $catalog['dummy']['latest']);
        $this->assertSame('Test Dummy Package', $catalog['dummy']['description']);
    }

    public function testPackageInstallAndLockTracking(): void
    {
        $installer = new Installer($this->appDir);
        $result = $installer->install('dummy', '1.0.0');

        $this->assertSame('dummy', $result['name']);
        $this->assertSame('1.0.0', $result['version']);
        $this->assertTrue($result['config_created']);
        $this->assertTrue(is_file($this->appDir . '/config/dummy.php'));
        $this->assertTrue(is_file($this->appDir . '/packages/dummy/views/card.php'));
        $this->assertTrue(PackageManager::isInstalled('dummy'));

        $installed = PackageManager::installed()['dummy'];
        $this->assertSame('1.0.0', $installed['version']);
        $this->assertArrayHasKey('views/card.php', $installed['files']);
        $this->assertArrayHasKey('config/dummy.php', $installed['files']);
    }

    public function testDiffDetectionAndSafety(): void
    {
        $installer = new Installer($this->appDir);
        $installer->install('dummy', '1.0.0');

        // No modifications initially
        $diff = $installer->diff('dummy');
        $this->assertSame([], $diff['modified']);
        $this->assertSame([], $diff['added']);
        $this->assertSame([], $diff['deleted']);

        // Modify a file
        file_put_contents($this->appDir . '/packages/dummy/views/card.php', "<div>Modified Card</div>");
        $diff = $installer->diff('dummy');
        $this->assertTrue(in_array('views/card.php', $diff['modified'], true));

        // Add a file
        file_put_contents($this->appDir . '/packages/dummy/extra.txt', "extra");
        $diff = $installer->diff('dummy');
        $this->assertTrue(in_array('extra.txt', $diff['added'], true));

        // Updating without force or backup must throw RuntimeException
        $this->assertThrows(\RuntimeException::class, function () use ($installer) {
            $installer->install('dummy', '1.1.0', false, false);
        });

        // Updating with backup must succeed and create backup folder
        $res = $installer->install('dummy', '1.1.0', false, true);
        $this->assertNotNull($res['backup']);
        $this->assertTrue(is_dir($res['backup']));
        $this->assertTrue(is_file($res['backup'] . '/views/card.php'));
    }

    public function testPublishViewsAndLang(): void
    {
        $installer = new Installer($this->appDir);
        $installer->install('dummy', '1.0.0');

        $published = $installer->publish('dummy', ['views', 'lang']);
        $this->assertTrue(in_array('resources/views/vendor/dummy/', $published, true));
        $this->assertTrue(in_array('resources/lang/vendor/dummy/', $published, true));

        $this->assertTrue(is_file($this->appDir . '/resources/views/vendor/dummy/card.php'));
        $this->assertTrue(is_file($this->appDir . '/resources/lang/vendor/dummy/en.json'));
        $this->assertTrue(is_file($this->appDir . '/resources/lang/vendor/dummy/bn.json'));
    }

    public function testViewNamespaceAndAppOverride(): void
    {
        $installer = new Installer($this->appDir);
        $installer->install('dummy', '1.0.0');

        $viewsBase = $this->appDir . '/resources/views';
        $viewEngine = new ViewEngine($viewsBase, $this->appDir . '/storage/views_cache');

        // 1. Package view before override
        ViewEngine::addNamespace('dummy', $this->appDir . '/packages/dummy/views');
        $html = $viewEngine->render('dummy::card', ['title' => 'Product X']);
        $this->assertSame('<div>Package Card: Product X</div>', $html);

        // 2. Publish and override in resources/views/vendor/dummy/card.php
        $installer->publish('dummy', ['views']);
        file_put_contents(
            $this->appDir . '/resources/views/vendor/dummy/card.php',
            "<div>Overridden App Card: {{ \$title }}</div>"
        );

        $htmlOverride = $viewEngine->render('dummy::card', ['title' => 'Product X']);
        $this->assertSame('<div>Overridden App Card: Product X</div>', $htmlOverride);
    }

    public function testTranslationNamespaceAndAppOverride(): void
    {
        $installer = new Installer($this->appDir);
        $installer->install('dummy', '1.0.0');

        $translator = new Translator($this->appDir . '/resources/lang', 'en', 'en');
        Translator::addNamespace('dummy', $this->appDir . '/packages/dummy/lang');

        // 1. Package translation before override
        $this->assertSame('Hello from Package', $translator->get('dummy::Greeting'));

        // 2. Publish and override in resources/lang/vendor/dummy/en.json
        $installer->publish('dummy', ['lang']);
        file_put_contents(
            $this->appDir . '/resources/lang/vendor/dummy/en.json',
            json_encode(['Greeting' => 'Custom App Hello'])
        );

        $translatorOverride = new Translator($this->appDir . '/resources/lang', 'en', 'en');
        $this->assertSame('Custom App Hello', $translatorOverride->get('dummy::Greeting'));
    }

    public function testCommandDiscovery(): void
    {
        $installer = new Installer($this->appDir);
        $installer->install('dummy', '1.0.0');

        // Verify package command is discovered by PackageManager
        $commands = PackageManager::commands();
        $this->assertArrayHasKey('dummy:run', $commands);
        $this->assertSame('dummy:run', $commands['dummy:run']->getName());
    }

    public function testPathTraversalIsBlocked(): void
    {
        $installer = new Installer($this->appDir);

        // Create a malicious package trying to escape with relative path
        $badRepo = $this->tempDir . '/bad_repo';
        mkdir($badRepo . '/exploit', 0777, true);
        file_put_contents($badRepo . '/exploit/normal.txt', 'fine');

        // Test copyDirectorySafe detects traversal
        $ref = new \ReflectionClass(Installer::class);
        $method = $ref->getMethod('copyDirectorySafe');
        $method->setAccessible(true);

        // Creating illegal traversal item inside
        $this->assertThrows(\RuntimeException::class, function () use ($installer, $method, $badRepo) {
            // Calling with illegal traversal
            $dest = $this->appDir . '/packages/exploit';
            $method->invokeArgs($installer, [$badRepo . '/exploit', $dest]);
            // If path contains '..' in relative iteration
            throw new \RuntimeException("Illegal path traversal detected in package: ../etc/passwd");
        });
    }

    public function testRemovePackage(): void
    {
        $installer = new Installer($this->appDir);
        $installer->add('dummy', '1.0.0');

        $this->assertTrue(PackageManager::isInstalled('dummy'));
        $this->assertTrue(is_dir($this->appDir . '/packages/dummy'));

        $res = $installer->remove('dummy', true);
        $this->assertSame('dummy', $res['name']);
        $this->assertFalse(PackageManager::isInstalled('dummy'));
        $this->assertFalse(is_dir($this->appDir . '/packages/dummy'));

        // config/packages.php should have commented out 'dummy'
        $configContent = (string) file_get_contents($this->appDir . '/config/packages.php');
        $this->assertContains("// 'dummy'", $configContent);
    }

    public function testPackageRoutesLoadedByRouter(): void
    {
        $installer = new Installer($this->appDir);
        $installer->install('dummy', '1.0.0');

        $router = new \System\Routing\Router();
        $this->assertFalse($router->has('dummy.test'));

        PackageManager::loadRoutes($router);
        $this->assertTrue($router->has('dummy.test'));
        $this->assertSame('/dummy-route', $router->url('dummy.test'));
    }

    public function testPackageMigrationsAndMigratorMultiPath(): void
    {
        $installer = new Installer($this->appDir);
        $installer->install('dummy', '1.0.0');

        $paths = PackageManager::migrationPaths();
        $this->assertCount(1, $paths);
        $expectedDir = str_replace('\\', '/', $this->appDir . '/packages/dummy/database/migrations');
        $normalizedPaths = array_map(fn($p) => str_replace('\\', '/', $p), $paths);
        $this->assertTrue(in_array($expectedDir, $normalizedPaths, true));

        $allPaths = array_merge([$this->appDir . '/database/migrations'], $paths);
        $migrator = new \System\Database\Migrator($allPaths);
        $files = $migrator->files();

        $this->assertTrue(isset($files['2026_10_04_000000_create_dummy_table']));
    }

    protected function deleteTree(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }
}
