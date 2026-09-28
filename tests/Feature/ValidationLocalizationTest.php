<?php

namespace Tests\Feature;

use Tests\TestCase;

class ValidationLocalizationTest extends TestCase
{
    public function test_validation_attributes_are_localized_in_every_supported_language(): void
    {
        $expectations = [
            'hy' => [
                'payment_method_id' => 'Վճարման եղանակ դաշտը պարտադիր է:',
                'translations.0.name' => 'Անուն դաշտը պարտադիր է:',
            ],
            'en' => [
                'payment_method_id' => 'The payment method field is required.',
                'translations.0.name' => 'The name field is required.',
            ],
            'ru' => [
                'payment_method_id' => 'Поле способ оплаты обязательно.',
                'translations.0.name' => 'Поле название обязательно.',
            ],
        ];

        foreach ($expectations as $locale => $messages) {
            app()->setLocale($locale);

            $errors = validator([], [
                'payment_method_id' => ['required'],
                'translations.0.name' => ['required'],
            ])->errors();

            foreach ($messages as $attribute => $message) {
                $this->assertSame($message, $errors->first($attribute));
            }
        }
    }

    public function test_supported_locales_have_matching_validation_rule_and_attribute_keys(): void
    {
        $english = trans('validation', [], 'en');
        $englishRuleKeys = array_values(array_diff(array_keys($english), ['attributes', 'custom']));
        $englishAttributeKeys = array_keys($english['attributes']);

        foreach (['hy', 'ru'] as $locale) {
            $translation = trans('validation', [], $locale);
            $ruleKeys = array_values(array_diff(array_keys($translation), ['attributes', 'custom']));

            $this->assertEqualsCanonicalizing($englishRuleKeys, $ruleKeys);
            $this->assertEqualsCanonicalizing($englishAttributeKeys, array_keys($translation['attributes']));
        }
    }
}
