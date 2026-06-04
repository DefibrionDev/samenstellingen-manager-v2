<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;

final readonly class AddWooStoreHandler
{
    public function __construct(private WooCommerceStoreRepository $repository)
    {
    }

    public function __invoke(AddWooStore $command): WooCommerceStore
    {
        if (trim($command->name) === '') {
            throw InvalidWooStoreException::emptyField('name');
        }
        if (trim($command->consumerKey) === '') {
            throw InvalidWooStoreException::emptyField('consumer-key');
        }
        if (trim($command->consumerSecret) === '') {
            throw InvalidWooStoreException::emptyField('consumer-secret');
        }
        if (!str_starts_with($command->baseUrl, 'https://')) {
            throw InvalidWooStoreException::nonHttpsUrl($command->baseUrl);
        }
        if ($this->repository->findByName($command->name) !== null) {
            throw InvalidWooStoreException::duplicateName($command->name);
        }

        $baseUrl = rtrim($command->baseUrl, '/');

        return $this->repository->save(new WooCommerceStore(
            null,
            $command->name,
            $baseUrl,
            $command->consumerKey,
            $command->consumerSecret,
            $command->afasItemcodeMetaKey,
        ));
    }
}
