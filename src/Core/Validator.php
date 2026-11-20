<?php

namespace App\Core;

// Lightweight field validator (LeasePro convention). Rules per field are pipe-separated, e.g.
// 'required|max:190', 'nullable|int|min:0', 'nullable|in:draft,published', 'nullable|date'.
// Strings are trimmed. Returns only the validated fields; throws 422 with per-field errors.
class Validator
{
    private array $data;
    private array $out = [];
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data, array $rules): array
    {
        $v = new self($data);
        foreach ($rules as $field => $ruleset) {
            $v->applyRules($field, explode('|', $ruleset));
        }
        if ($v->errors) {
            throw new AppException('Validation failed', 422, $v->errors);
        }
        return $v->out;
    }

    private function applyRules(string $field, array $rules): void
    {
        $value = $this->data[$field] ?? null;
        if (is_string($value)) {
            $value = trim($value);
        }
        $isPresent = array_key_exists($field, $this->data) && $value !== '' && $value !== null;
        $label = str_replace('_', ' ', $field);

        if (!$isPresent) {
            if (in_array('required', $rules, true)) {
                $this->errors[$field][] = ucfirst($label) . ' is required';
            } elseif (array_key_exists($field, $this->data)) {
                $this->out[$field] = null; // explicitly cleared
            }
            return;
        }

        foreach ($rules as $rule) {
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
            switch ($name) {
                case 'string':
                    if (!is_string($value) && !is_numeric($value)) {
                        $this->errors[$field][] = ucfirst($label) . ' must be text';
                    } else {
                        $value = (string) $value;
                    }
                    break;
                case 'int':
                    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $this->errors[$field][] = ucfirst($label) . ' must be a whole number';
                    } else {
                        $value = (int) $value;
                    }
                    break;
                case 'bool':
                    $b = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                    if ($b === null) {
                        $this->errors[$field][] = ucfirst($label) . ' must be true or false';
                    } else {
                        $value = $b;
                    }
                    break;
                case 'min':
                    if (is_int($value) && $value < (int) $param) {
                        $this->errors[$field][] = ucfirst($label) . " must be at least $param";
                    } elseif (is_string($value) && mb_strlen($value) < (int) $param) {
                        $this->errors[$field][] = ucfirst($label) . " must be at least $param characters";
                    }
                    break;
                case 'max':
                    if (is_int($value) && $value > (int) $param) {
                        $this->errors[$field][] = ucfirst($label) . " must be at most $param";
                    } elseif (is_string($value) && mb_strlen($value) > (int) $param) {
                        $this->errors[$field][] = ucfirst($label) . " must be at most $param characters";
                    }
                    break;
                case 'in':
                    if (!in_array((string) $value, explode(',', (string) $param), true)) {
                        $this->errors[$field][] = ucfirst($label) . " must be one of: $param";
                    }
                    break;
                case 'date':
                    $d = \DateTime::createFromFormat('Y-m-d', (string) $value);
                    if (!$d || $d->format('Y-m-d') !== (string) $value) {
                        $this->errors[$field][] = ucfirst($label) . ' must be a date (YYYY-MM-DD)';
                    }
                    break;
                case 'slug':
                    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value)) {
                        $this->errors[$field][] = ucfirst($label) . ' may only contain lowercase letters, numbers and single hyphens';
                    }
                    break;
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $this->errors[$field][] = ucfirst($label) . ' must be a valid email';
                    } else {
                        $value = strtolower((string) $value);
                    }
                    break;
            }
        }
        $this->out[$field] = $value;
    }
}
