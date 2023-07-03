<?php
declare(strict_types=1);

namespace SwiftOtter\FriendRecommendations\Model\Resolver;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\CustomerGraphQl\Model\Customer\GetCustomer;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use SwiftOtter\FriendRecommendations\Api\RecommendationListRepositoryInterface;

class RecommendationLists implements ResolverInterface
{
    public function __construct(
        private readonly RecommendationListRepositoryInterface $recommendationListRepository,
        private readonly SearchCriteriaBuilder                 $searchCriteriaBuilder,
        private readonly getCustomer                           $getCustomer,
        private readonly ProductRepositoryInterface            $productRepository,
        private readonly ImageHelper $imageHelper
    ) {
    }

    /**
     * {@inheritdoc}
     * @param ContextInterface $context
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $isLoggedIn = $context->getExtensionAttributes()->getIsCustomer();
        if (!$isLoggedIn) {
            throw new GraphQlNoSuchEntityException(__('You must be logged in to view your recommendation lists'));
        }
        $customer = $this->getCustomer->execute($context);
        $email = $customer->getEmail();
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('email', $email)->create();

        $recommendationLists = $this->recommendationListRepository->getList($searchCriteria)->getItems();
        if (empty($recommendationLists)) {
            throw new GraphQlNoSuchEntityException(__('No recommendation lists for this user'));
        }
        $getProducts = $info->getFieldSelection()['products'] ?? false;
        $recommendationListsArray = [];
        foreach ($recommendationLists as $recommendationList) {
            $recommendationListsArray[] = [
                'friendName' => $recommendationList->getFriendName(),
                'title' => $recommendationList->getTitle(),
                'note' => $recommendationList->getNote(),
                'products' => $getProducts ? $this->getRecommendationListProductIds($recommendationList) : []
            ];
        }

        return $recommendationListsArray;
    }

    /**
     * @throws NoSuchEntityException
     */
    private function getRecommendationListProductIds($recommendationList): array
    {
        $listProducts = [];
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('recommendation_list_ids', [$recommendationList->getId()])->create();
        $products = $this->productRepository->getList($searchCriteria)->getItems();
        foreach ($products as $product) {
            $listProducts[] = [
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'thumbnailUrl' => $this->imageHelper->init($product, 'product_thumbnail_image')->getUrl()
            ];
        }
        return $listProducts;
    }
}
