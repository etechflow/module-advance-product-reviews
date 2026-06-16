<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Test\Unit\Model;

use ETechFlow\AdvancedProductReviews\Model\LicenseValidator;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the hybrid HMAC + portal license validator.
 *
 * Confirms (per LICENSING_PROTOCOL.md):
 *  - per-module HMAC key validates on a production host;
 *  - the shared bundle key activates the module;
 *  - dev hosts + "Production Environment = No" bypass licensing;
 *  - wrong keys silently fail;
 *  - computeKey/computeBundleKey are deterministic + URL-safe + distinct.
 */
class LicenseValidatorTest extends TestCase
{
    private const HOST = 'coolstore.com';

    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;
    /** @var StoreManagerInterface&MockObject */
    private $storeManager;
    /** @var CacheInterface&MockObject */
    private $cache;
    /** @var Curl&MockObject */
    private $curl;
    /** @var WriterInterface&MockObject */
    private $writer;

    private LicenseValidator $validator;

    protected function setUp(): void
    {
        $this->scopeConfig  = $this->createMock(ScopeConfigInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->cache        = $this->createMock(CacheInterface::class);
        $this->curl         = $this->createMock(Curl::class);
        $this->writer       = $this->createMock(WriterInterface::class);

        $this->validator = new LicenseValidator(
            $this->scopeConfig,
            $this->storeManager,
            $this->cache,
            $this->curl,
            $this->writer
        );

        $this->cache->method('load')->willReturn(false);
    }

    /**
     * Point the validator at a given store host.
     */
    private function withHost(string $host): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://' . $host . '/');
        $this->storeManager->method('getStore')->willReturn($store);
    }

    /**
     * Drive scopeConfig->getValue() from a path => value map.
     *
     * @param array<string,mixed> $map
     */
    private function withConfig(array $map): void
    {
        $defaults = [
            LicenseValidator::XML_PATH_PRODUCTION_ENVIRONMENT => '1',
            LicenseValidator::XML_PATH_LICENSE_KEY            => '',
            LicenseValidator::XML_PATH_BUNDLE_LICENSE_KEY     => '',
            LicenseValidator::XML_PATH_ISSUED_KEY             => '',
            LicenseValidator::XML_PATH_ISSUED_AT              => '0',
            LicenseValidator::XML_PATH_IP_BLOCKED             => '0',
            LicenseValidator::XML_PATH_PORTAL_URL             => '',
        ];
        $merged = array_merge($defaults, $map);
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn ($path) => $merged[$path] ?? null
        );
    }

    public function testCanonicalizeStripsWwwAndLowercases(): void
    {
        self::assertSame('coolstore.com', $this->validator->canonicalize('WWW.CoolStore.com'));
        self::assertSame('shop.example.org', $this->validator->canonicalize('Shop.Example.org'));
    }

    public function testComputeKeyIsDeterministicUrlSafeAndDistinctFromBundle(): void
    {
        $key1 = $this->validator->computeKey(self::HOST);
        $key2 = $this->validator->computeKey(self::HOST);

        self::assertNotSame('', $key1);
        self::assertSame($key1, $key2, 'computeKey must be deterministic');
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $key1, 'key must be URL-safe base64');
        self::assertNotSame(
            $key1,
            $this->validator->computeBundleKey(self::HOST),
            'per-module key and bundle key must differ'
        );
    }

    public function testProductionEnvironmentOffBypassesLicensing(): void
    {
        $this->withHost(self::HOST);
        $this->withConfig([LicenseValidator::XML_PATH_PRODUCTION_ENVIRONMENT => '0']);

        self::assertTrue($this->validator->isValid());
    }

    public function testDevelopmentHostBypassesLicensingEvenInProduction(): void
    {
        $this->withHost('staging-shop.test');
        $this->withConfig([LicenseValidator::XML_PATH_PRODUCTION_ENVIRONMENT => '1']);

        self::assertTrue($this->validator->isValid());
    }

    public function testValidPerModuleKeyActivatesOnProductionHost(): void
    {
        $this->withHost(self::HOST);
        $key = $this->validator->computeKey(self::HOST);
        $this->withConfig([LicenseValidator::XML_PATH_LICENSE_KEY => $key]);

        self::assertTrue($this->validator->isValid());
    }

    public function testBundleKeyActivatesWhenPerModuleKeyDoesNotMatch(): void
    {
        $this->withHost(self::HOST);
        $bundleKey = $this->validator->computeBundleKey(self::HOST);
        // Per-module field holds a non-empty, non-matching value so checkKey
        // falls through to the bundle branch.
        $this->withConfig([
            LicenseValidator::XML_PATH_LICENSE_KEY        => 'not-the-per-module-key',
            LicenseValidator::XML_PATH_BUNDLE_LICENSE_KEY => $bundleKey,
        ]);

        self::assertTrue($this->validator->isValid());
    }

    public function testWrongKeyFailsOnProductionHost(): void
    {
        $this->withHost(self::HOST);
        $this->withConfig([LicenseValidator::XML_PATH_LICENSE_KEY => 'totally-wrong-key']);

        self::assertFalse($this->validator->isValid());
    }

    public function testEmptyKeyWithoutIpBlockStaysLocked(): void
    {
        $this->withHost(self::HOST);
        $this->withConfig([
            LicenseValidator::XML_PATH_LICENSE_KEY => '',
            LicenseValidator::XML_PATH_IP_BLOCKED  => '0',
        ]);

        self::assertFalse($this->validator->isValid());
    }
}
