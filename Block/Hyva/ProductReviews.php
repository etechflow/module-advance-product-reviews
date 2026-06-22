<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Block\Hyva;

use ETechFlow\AdvancedProductReviews\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Hyvä PDP block. Supplies the product, store code and GraphQL endpoint to the
 * Tailwind/Alpine template, which then drives the whole reviews UI through this
 * module's GraphQL API.
 *
 * This block lives in the base module so the extension ships as a single
 * package, but it only renders when a Hyvä theme is active (see isHyvaTheme),
 * so Luma and other standard themes are completely unaffected.
 */
class ProductReviews extends Template
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param DesignInterface $design
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly DesignInterface $design,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether the active storefront theme is Hyvä (or a child of a Hyvä theme).
     *
     * Walks the theme inheritance chain looking for "hyva" in the theme code or
     * path, so Hyvä child themes are detected too. Dependency-free: it never
     * references any Hyva_Theme class, so it is safe on stores without Hyvä.
     *
     * @return bool
     */
    public function isHyvaTheme(): bool
    {
        $theme = $this->design->getDesignTheme();
        while ($theme instanceof ThemeInterface) {
            $needle = strtolower((string) $theme->getCode() . '|' . (string) $theme->getThemePath());
            if (strpos($needle, 'hyva') !== false) {
                return true;
            }
            $theme = $theme->getParentTheme();
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isModuleEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * @return bool
     */
    public function isTranslationEnabled(): bool
    {
        return $this->config->isTranslationEnabled();
    }

    /**
     * @return bool
     */
    public function isQaEnabled(): bool
    {
        return $this->config->isFlag(Config::XML_PATH_ENABLE_QA);
    }

    /**
     * @return ProductInterface|null
     */
    public function getProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof ProductInterface ? $product : null;
    }

    /**
     * @return int
     */
    public function getProductId(): int
    {
        $product = $this->getProduct();
        return $product ? (int) $product->getId() : 0;
    }

    /**
     * @return string
     */
    public function getProductSku(): string
    {
        $product = $this->getProduct();
        return $product ? (string) $product->getSku() : '';
    }

    /**
     * Current store code — sent as the GraphQL "Store" header for multi-store.
     *
     * @return string
     */
    public function getStoreCode(): string
    {
        try {
            return (string) $this->storeManager->getStore()->getCode();
        } catch (\Exception $e) {
            return 'default';
        }
    }

    /**
     * Absolute GraphQL endpoint URL.
     *
     * @return string
     */
    public function getGraphQlUrl(): string
    {
        return $this->getUrl('graphql', ['_secure' => true]);
    }
}
