<?php

namespace SwiftOtter\FriendRecommendations\Model\Resolver;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use SwiftOtter\FriendRecommendations\Api\Data\RecommendationListInterfaceFactory as RecommendationListFactory;
use SwiftOtter\FriendRecommendations\Api\Data\RecommendationListProductInterfaceFactory as RecommendationListProductFactory;
use SwiftOtter\FriendRecommendations\Api\RecommendationListProductRepositoryInterface;
use SwiftOtter\FriendRecommendations\Api\RecommendationListRepositoryInterface as RecommendationListRepository;

class CreateRecommendationList implements ResolverInterface
{
    public function __construct(
        private readonly RecommendationListRepository                 $recommendationListRepository,
        private readonly RecommendationListFactory                    $recommendationListFactory,
        private readonly RecommendationListProductFactory             $recommendationListProductFactory,
        private readonly RecommendationListProductRepositoryInterface $recommendationListProductRepository
    ) {
    }

    /**
     * {@inheritdoc}
     * @param ContextInterface $context
     * @throws GraphQlInputException
     * @throws CouldNotSaveException
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
            throw new GraphQlInputException(__('You must be logged in to create a recommendation list.'));
        }
        //Check if args are not empty - email, friendName, productSkus are required
        if (empty($args['email']) || empty($args['friendName']) || empty($args['productSkus'])) {
            throw new GraphQlInputException(__('Email, friendName, and productSkus are required.'));
        }

        $email = $args['email'];
        $friendName = $args['friendName'];
        $title = $args['title'] ?? '';
        $note = $args['note'] ?? '';
        $productSkus = $args['productSkus'];

        $recommendationList = $this->recommendationListFactory->create();
        $recommendationList->setEmail($email);
        $recommendationList->setFriendName($friendName);
        $recommendationList->setTitle($title);
        $recommendationList->setNote($note);

        $savedList = $this->recommendationListRepository->save($recommendationList);

        foreach ($productSkus as $sku) {
            $recommendationListProduct = $this->recommendationListProductFactory->create();
            $recommendationListProduct->setSku($sku);
            $recommendationListProduct->setListId($savedList->getId());
            $this->recommendationListProductRepository->save($recommendationListProduct);
        }

        return [
            'email' => $savedList->getEmail(),
            'friendName' => $savedList->getFriendName(),
            'title' => $savedList->getTitle(),
            'note' => $savedList->getNote(),
        ];
    }
}
