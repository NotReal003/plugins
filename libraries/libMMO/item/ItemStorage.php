<?php
/**
 *   _ _ _     __  __ __  __  ____
 *  | (_) |   |  \/  |  \/  |/ __ \
 *  | |_| |__ | \  / | \  / | |  | |
 *  | | | '_ \| |\/| | |\/| | |  | |
 *  | | | |_) | |  | | |  | | |__| |
 *  |_|_|_.__/|_|  |_|_|  |_|\____/
 *
 * Copyright (C) 2016-2024 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */

declare(strict_types=1);

namespace libMMO\item;

use Closure;
use Generator;
use GlobalLogger;
use libMMO\utils\Database;
use libMMO\utils\Utils;
use pocketmine\item\Item;
use pocketmine\nbt\tag\IntTag;
use poggit\libasynql\SqlError;
use SOFe\AwaitGenerator\Await;
use Throwable;

class ItemStorage
{
    public const ITEM_VALIDATED = 0;
    public const ITEM_INVALID = 1;
    public const ITEM_INVALID_ID = 2;
    public const EXECUTION_FAILED = 3;

    public const VALIDATION_ID = 'ValidationId';
    public const ORIGIN = 'ValidationOrigin';
    public const CREATION_DATE = 'ValidationDate';

    private static array $pendingValidations = [];

    /**
     * Tries to remove the given item's validation id in the database, function is used only when the server
     * wants to validate the item *and* continues on doing its tasks (Deposit money, Spawn mini-helpers).
     *
     * Please note that this function will remove the item by using the <code>Item->pop()</code> function.
     * It should be noted that this function will have to be called during an interaction of an item - not
     * anywhere else.
     *
     * @param Item $item Any valid items to check for.
     * @param Closure $closure A callback function with signature of <code>function(int, ?Item) : void{}</code>
     */
    public static function isValidAndRemove(Item $item, Closure $closure): void
    {
        $validationId = $item->getNamedTag()->getInt(ItemStorage::VALIDATION_ID, -1);
        $creationDate = $item->getNamedTag()->getInt(ItemStorage::CREATION_DATE, -1);
        $origin = $item->getNamedTag()->getString(ItemStorage::ORIGIN, '');

        if ($validationId !== -1 && !isset(self::$pendingValidations[$validationId])) {
            $failCopy = clone $item;

            $item->pop();

            self::$pendingValidations[$validationId] = true;
            Await::f2c(function () use ($validationId, $closure, $origin, $creationDate, $item) {
                Database::executeChange(Database::REMOVE_ITEM_STORAGE, ['id' => $validationId], yield, yield Await::REJECT);

                $affectedRows = yield Await::ONCE;

                unset(self::$pendingValidations[$validationId]);

                if ($affectedRows === 0) {
                    GlobalLogger::get()->info("[isValidAndRemove] Item is invalid, data=$validationId, origin=$origin, date=$creationDate");

                    $closure(self::ITEM_INVALID, null);
                } else {
                    GlobalLogger::get()->info("[isValidAndRemove] Item is valid, data=$validationId, origin=$origin, date=$creationDate");

                    $closure(self::ITEM_VALIDATED, $item);
                }
            }, catches: function (Throwable $error) use ($validationId, $closure, $origin, $creationDate, $failCopy): void {
                unset(self::$pendingValidations[$validationId]);

                if (!($error instanceof SqlError)) {
                    return;
                }

                GlobalLogger::get()->error("[isValidAndRemove] Validation check failed for $validationId, origin=$origin, date=$creationDate");
                GlobalLogger::get()->logException($error);

                $closure(self::EXECUTION_FAILED, $failCopy);
            });
        } else {
            $closure(self::ITEM_INVALID_ID, $item);
        }
    }

    /**
     * Only check if the item is valid, if you want to do something similar to validate-then-execute, please use
     * {@link ItemStorage::isValidAndRemove()}. This function is intended to *validate* if the given item's validation
     * id do exists in the database.
     *
     * Unlike the {@link ItemStorage::isValidAndRemove()} method, this method can be invoked anywhere in your
     * project's scope. It is not constrained by the child's invocation.
     *
     * @param Item $item Any valid items to check for.
     * @param Closure $closure A callback function with signature of <code>function(int) : void{}</code>
     */
    public static function isValid(Item $item, Closure $closure): void
    {
        $validationId = $item->getNamedTag()->getInt(ItemStorage::VALIDATION_ID, -1);
        $creationDate = $item->getNamedTag()->getInt(ItemStorage::CREATION_DATE, -1);
        $origin = $item->getNamedTag()->getString(ItemStorage::ORIGIN, '');

        if ($validationId !== -1) {
            Await::f2c(static function () use ($validationId, $closure, $origin, $creationDate): Generator {
                Database::executeSelect(Database::EXISTS_ITEM_STORAGE, ['id' => $validationId], yield, yield Await::REJECT);

                $rows = yield Await::ONCE;
                if (count($rows) === 0) {
                    GlobalLogger::get()->info("[isValid] Item is invalid, data=$validationId, origin=$origin, date=$creationDate");

                    $closure(self::ITEM_INVALID);
                } else {
                    GlobalLogger::get()->info("[isValid] Item is valid, data=$validationId, origin=$origin, date=$creationDate");

                    $closure(self::ITEM_VALIDATED);
                }
            }, catches: function (Throwable $error) use ($validationId, $closure, $origin, $creationDate): void {
                if (!($error instanceof SqlError)) {
                    return;
                }

                GlobalLogger::get()->error("[isValid] Validation check failed for $validationId, origin=$origin, date=$creationDate");
                GlobalLogger::get()->logException($error);

                $closure(self::EXECUTION_FAILED);
            });
        } else {
            $closure(self::ITEM_INVALID_ID);
        }
    }

    /**
     * @param Item $item
     * @param string $origin
     * @param Closure $closure (Item)
     */
    public static function createValidationId(Item $item, string $origin, Closure $closure): void
    {
        Database::executeInsert(Database::ADD_ITEM_STORAGE, ['origin' => $origin], static function (int $insertId, int $affectedRows) use ($item, $closure, $origin): void {
            $item->getNamedTag()->setInt(self::VALIDATION_ID, $insertId);
            $item->getNamedTag()->setString(self::ORIGIN, $origin);
            $item->getNamedTag()->setInt(self::CREATION_DATE, time());

            $closure($item);
        });
    }

    public static function hasValidationId(Item $item): bool
    {
        return Utils::hasTag($item->getNamedTag(), self::VALIDATION_ID, IntTag::class);
    }

    /**
     * @param Item $item
     * @param Closure|null $onComplete
     */
    public static function removeValidationId(Item $item, ?Closure $onComplete = null): void
    {
        $validationId = $item->getNamedTag()->getInt(self::VALIDATION_ID, -1);

        if ($validationId !== -1) {
            $item->getNamedTag()->removeTag(self::VALIDATION_ID);

            Database::executeChange(Database::REMOVE_ITEM_STORAGE, ['id' => $validationId], static function (int $affectedRows) use ($onComplete): void {
                if ($affectedRows > 0 && $onComplete !== null) {
                    $onComplete();
                }
            });
        }
    }
}