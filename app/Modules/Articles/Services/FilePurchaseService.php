<?php

declare(strict_types=1);

namespace SoulCMS\App\Modules\Articles\Services;

use SoulCMS\App\Modules\Articles\Repositories\ArticleRepository;
use SoulCMS\App\Modules\User\Repositories\UserRepository;
use SoulCMS\Database\Connection;

/** Handles paid attachment purchases using the existing users and file columns. */
final class FilePurchaseService
{
    public function __construct(
        private Connection $connection,
        private ArticleRepository $articles,
        private UserRepository $users,
    ) {
    }

    public function hasPurchased(int $fileId, string $nick): bool
    {
        $file = $this->articles->fileForPurchase($fileId);
        $nick = mb_strtolower(trim($nick));
        if ($file === null || $nick === '') {
            return false;
        }

        $buyers = array_map('mb_strtolower', array_filter(explode('.', (string) ($file['mua'] ?? ''))));

        return in_array($nick, $buyers, true);
    }

    public function purchase(int $fileId, int $buyerId): bool
    {
        if ($fileId < 1 || $buyerId < 1) {
            return false;
        }

        return $this->connection->transaction(function () use ($fileId, $buyerId): bool {
            $file = $this->articles->fileForPurchase($fileId);
            $buyer = $this->users->purchaseAccount($buyerId);

            if ($file === null || $buyer === null || (int) ($file['price'] ?? 0) <= 0) {
                return false;
            }

            $status = (string) ($file['status'] ?? 'private');
            if ($status === 'public' || !in_array($status, ['private', 'public'], true)) {
                return false;
            }

            if ((string) ($file['type'] ?? '') !== 's3') {
                return false;
            }

            $nick = mb_strtolower((string) $buyer['nick']);
            if ($nick === '' || $nick === mb_strtolower((string) ($file['author'] ?? ''))) {
                return false;
            }

            $rawBuyers = is_string($file['mua'] ?? null) ? (string) $file['mua'] : '';
            $buyers = array_values(array_unique(array_filter(
                array_map('mb_strtolower', explode('.', $rawBuyers)),
                static fn (string $existing): bool => $existing !== ''
            )));
            if (in_array($nick, $buyers, true)) {
                return false;
            }

            if (strlen($rawBuyers) + strlen($nick) + ($rawBuyers === '' ? 0 : 1) > 255) {
                return false;
            }

            $price = (int) $file['price'];
            if (!$this->users->debitPointsIfSufficient($buyerId, $price)) {
                return false;
            }

            array_unshift($buyers, $nick);
            $newBuyers = implode('.', $buyers);
            if (!$this->articles->appendFilePurchaserIfUnchanged($fileId, $file['mua'] === null ? null : $rawBuyers, $newBuyers)) {
                $this->users->creditPoints($buyerId, $price);

                return false;
            }

            return true;
        });
    }
}
