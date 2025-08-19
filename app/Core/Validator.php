<?php
namespace App\Core;

class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleStr) {
            $rulesArr = explode('|', $ruleStr);
            $value = trim((string)($data[$field] ?? ''));
            foreach ($rulesArr as $rule) {
                if ($rule === 'required' && $value === '') {
                    $errors[$field] = 'required';
                    break;
                }
                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'email';
                    break;
                }
                if (str_starts_with($rule, 'min:')) {
                    $min = (int)substr($rule,4);
                    if (strlen($value) < $min) { $errors[$field] = 'min'; break; }
                }
                if (str_starts_with($rule, 'in:')) {
                    $allowed = explode(',', substr($rule, 3));
                    if (!in_array($value, $allowed, true)) { $errors[$field] = 'in'; break; }
                }
            }
        }
        return $errors;
    }
}
