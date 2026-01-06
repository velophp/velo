<?php

namespace Tests\Unit;

use App\FieldOptions\{FileFieldOption, EmailFieldOption, DatetimeFieldOption, TextFieldOption, NumberFieldOption, BoolFieldOption};
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FieldOptionValidationTest extends TestCase
{
    public function test_file_field_option_validation_rules()
    {
        $option = new FileFieldOption(
            allowedMimeTypes: ['image/jpeg', 'image/png'],
            maxSize: 5000000,
            minSize: 1000,
            multiple: true,
            maxFiles: 5
        );

        $rules = $option->getValidationRules();

        $this->assertArrayHasKey('allowedMimeTypes', $rules);
        $this->assertArrayHasKey('maxSize', $rules);
        $this->assertArrayHasKey('minSize', $rules);
        $this->assertArrayHasKey('multiple', $rules);
        $this->assertArrayHasKey('maxFiles', $rules);
        $this->assertArrayHasKey('generateThumbnail', $rules);
        $this->assertArrayHasKey('thumbnailSizes', $rules);
    }

    public function test_email_field_option_validation_rules()
    {
        $option = new EmailFieldOption(
            allowedDomains: ['gmail.com', 'company.com']
        );

        $rules = $option->getValidationRules();

        $this->assertArrayHasKey('allowedDomains', $rules);
        $this->assertArrayHasKey('blockedDomains', $rules);
        $this->assertContains('nullable', $rules['allowedDomains']);
        $this->assertContains('array', $rules['allowedDomains']);
    }

    public function test_text_field_option_validation_rules()
    {
        $option = new TextFieldOption(
            minLength: 5,
            maxLength: 100,
            pattern: '^[a-zA-Z]+$'
        );

        $rules = $option->getValidationRules();

        $this->assertArrayHasKey('minLength', $rules);
        $this->assertArrayHasKey('maxLength', $rules);
        $this->assertArrayHasKey('pattern', $rules);
    }

    public function test_number_field_option_validation_rules()
    {
        $option = new NumberFieldOption(
            min: 0,
            max: 100,
            allowDecimals: true
        );

        $rules = $option->getValidationRules();

        $this->assertArrayHasKey('min', $rules);
        $this->assertArrayHasKey('max', $rules);
        $this->assertArrayHasKey('allowDecimals', $rules);
        $this->assertContains('boolean', $rules['allowDecimals']);
    }

    public function test_datetime_field_option_validation_rules()
    {
        $option = new DatetimeFieldOption(
            minDate: '2024-01-01',
            maxDate: '2025-12-31'
        );

        $rules = $option->getValidationRules();

        $this->assertArrayHasKey('minDate', $rules);
        $this->assertArrayHasKey('maxDate', $rules);
    }

    public function test_bool_field_option_validation_rules()
    {
        $option = new BoolFieldOption();
        $rules = $option->getValidationRules();

        $this->assertEmpty($rules);
    }

    public function test_validation_rules_can_be_used_with_validator()
    {
        $option = new TextFieldOption(
            minLength: 5,
            maxLength: 10,
            pattern: '^[a-z]+$'
        );

        $rules = $option->getValidationRules();
        
        $validator = Validator::make([
            'minLength' => 5,
            'maxLength' => 10,
            'pattern' => '^[a-z]+$'
        ], $rules);

        $this->assertFalse($validator->fails());
    }
}
