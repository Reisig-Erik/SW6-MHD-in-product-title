<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Core\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * Rule to check if cart line items have MHD (best-before) dates matching specified criteria.
 *
 * This rule allows filtering cart items based on their MHD date, supporting:
 * - Products expiring before/after a specific date
 * - Products with/without MHD dates
 * - Date comparison operators (=, !=, <, <=, >, >=, empty)
 */
class LineItemMhdDateRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemMhdDate';

    protected string $operator = self::OPERATOR_EQ;
    protected ?string $mhdDate = null;

    public function __construct(string $operator = self::OPERATOR_EQ, ?string $mhdDate = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->mhdDate = $mhdDate;
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::datetimeOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['mhdDate'] = RuleConstraints::datetime();

        return $constraints;
    }

    public function match(RuleScope $scope): bool
    {
        try {
            $ruleValue = $this->buildDate($this->mhdDate);
        } catch (\Exception) {
            return false;
        }

        if ($scope instanceof LineItemScope) {
            return $this->matchesMhdDate($scope->getLineItem(), $ruleValue);
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->matchesMhdDate($lineItem, $ruleValue)) {
                return true;
            }
        }

        return false;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER, true)
            ->dateTimeField('mhdDate');
    }

    public function getName(): string
    {
        return self::RULE_NAME;
    }

    private function matchesMhdDate(LineItem $lineItem, ?\DateTime $ruleValue): bool
    {
        try {
            // Get custom fields from line item payload
            $customFields = $lineItem->getPayloadValue('customFields');

            if ($customFields === null) {
                return RuleComparison::isNegativeOperator($this->operator);
            }

            // Get the MHD date from custom fields
            $mhdDateString = $customFields['custom_product_mhd_date'] ?? null;

            if ($mhdDateString === null) {
                return RuleComparison::isNegativeOperator($this->operator);
            }

            /** @var \DateTime $itemMhdDate */
            $itemMhdDate = $this->buildDate($mhdDateString);
        } catch (\Exception) {
            return false;
        }

        if ($ruleValue === null) {
            return false;
        }

        return RuleComparison::datetime($itemMhdDate, $ruleValue, $this->operator);
    }

    private function buildDate(?string $dateString): ?\DateTime
    {
        if ($dateString === null) {
            return null;
        }

        return new \DateTime($dateString);
    }
}
