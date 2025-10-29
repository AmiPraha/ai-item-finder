<?php

namespace AmiPraha\AiItemFinder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AmiPraha\AiItemFinder\AiItemFinder setList(array $list)
 * @method static \AmiPraha\AiItemFinder\AiItemFinder setSearchedItem(string $searchedItemKey, mixed $searchedItemValue)
 * @method static \AmiPraha\AiItemFinder\AiItemFinder setAdditionalInstructions(string $instructions)
 * @method static \AmiPraha\AiItemFinder\AiItemFinder setSystemMessage(string $systemMessage)
 * @method static \AmiPraha\AiItemFinder\AiItemFinder setModel(string $model)
 * @method static array find()
 *
 * @see \AmiPraha\AiItemFinder\AiItemFinder
 */
class AiItemFinder extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'ai-item-finder';
    }
}
