<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceDeskSetting extends Model
{
    public const KEY_PROJECT_OPTIONS = 'project_options';
    public const KEY_TICKET_FORM_SCHEMA = 'ticket_form_schema';
    public const KEY_TICKET_CUSTOM_FIELDS = 'ticket_custom_fields';
    public const KEY_PUBLIC_LINK_EXPIRY_MINUTES = 'public_link_expiry_minutes';
    public const KEY_TICKET_TERMS_TEXT = 'ticket_terms_text';
    public const KEY_TICKET_TERMS_TRIGGER_STATUSES = 'ticket_terms_trigger_statuses';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            User::ROLE_CLIENT => User::roles()[User::ROLE_CLIENT],
            User::ROLE_INTERNAL => User::roles()[User::ROLE_INTERNAL],
            User::ROLE_VENDOR => User::roles()[User::ROLE_VENDOR],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function ticketFieldLabels(): array
    {
        $labels = [];

        foreach (self::ticketFieldDefinitions() as $fieldKey => $fieldDefinition) {
            $labels[$fieldKey] = $fieldDefinition['label'];
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    public static function ticketCustomFieldTypeLabels(): array
    {
        return [
            'text' => 'Single-line Text',
            'textarea' => 'Multi-line Text',
            'number' => 'Number',
            'date' => 'Date',
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public static function ticketCustomFields(): array
    {
        $stored = self::valueByKey(self::KEY_TICKET_CUSTOM_FIELDS);

        if (! is_array($stored['fields'] ?? null)) {
            return [];
        }

        /** @var array<int, mixed> $rawFields */
        $rawFields = $stored['fields'];

        return self::normalizeCustomFieldList($rawFields);
    }

    /**
     * @param  array<int, array{key?: string, label?: string, type?: string}>  $fields
     */
    public static function updateTicketCustomFields(array $fields): void
    {
        self::query()->updateOrCreate(
            ['key' => self::KEY_TICKET_CUSTOM_FIELDS],
            ['value' => ['fields' => self::normalizeCustomFieldList($fields)]]
        );
    }

    /**
     * @return array<string, array{label: string, type: string, builtin: bool}>
     */
    public static function ticketFieldDefinitions(): array
    {
        $definitions = [];

        foreach (self::builtInTicketFieldDefinitions() as $fieldKey => $fieldDefinition) {
            $definitions[$fieldKey] = [
                'label' => $fieldDefinition['label'],
                'type' => $fieldDefinition['type'],
                'builtin' => true,
            ];
        }

        foreach (self::ticketCustomFields() as $field) {
            $definitions[$field['key']] = [
                'label' => $field['label'],
                'type' => $field['type'],
                'builtin' => false,
            ];
        }

        return $definitions;
    }

    public static function publicLinkExpiryMinutes(): int
    {
        $stored = self::valueByKey(self::KEY_PUBLIC_LINK_EXPIRY_MINUTES);
        $value = (int) ($stored['minutes'] ?? self::defaultPublicLinkExpiryMinutes());

        if ($value < 1 || $value > 10080) {
            return self::defaultPublicLinkExpiryMinutes();
        }

        return $value;
    }

    public static function updatePublicLinkExpiryMinutes(int $minutes): void
    {
        $safeMinutes = max(1, min(10080, $minutes));

        self::query()->updateOrCreate(
            ['key' => self::KEY_PUBLIC_LINK_EXPIRY_MINUTES],
            ['value' => ['minutes' => $safeMinutes]]
        );
    }

    public static function ticketTermsText(): string
    {
        $stored = self::valueByKey(self::KEY_TICKET_TERMS_TEXT);
        $text = trim((string) ($stored['text'] ?? ''));

        return $text !== '' ? $text : self::defaultTicketTermsText();
    }

    public static function updateTicketTermsText(string $text): void
    {
        $normalized = trim($text);

        self::query()->updateOrCreate(
            ['key' => self::KEY_TICKET_TERMS_TEXT],
            ['value' => ['text' => $normalized !== '' ? $normalized : self::defaultTicketTermsText()]]
        );
    }

    /**
     * @return array<int, string>
     */
    public static function ticketTermsTriggerStatuses(): array
    {
        $stored = self::valueByKey(self::KEY_TICKET_TERMS_TRIGGER_STATUSES);

        if (! is_array($stored['statuses'] ?? null)) {
            return self::defaultTicketTermsTriggerStatuses();
        }

        /** @var array<int, mixed> $statuses */
        $statuses = $stored['statuses'];
        $normalized = self::normalizeStringList($statuses, []);

        return $normalized !== [] ? $normalized : [];
    }

    /**
     * @param  array<int, string>  $statuses
     */
    public static function updateTicketTermsTriggerStatuses(array $statuses): void
    {
        self::query()->updateOrCreate(
            ['key' => self::KEY_TICKET_TERMS_TRIGGER_STATUSES],
            ['value' => ['statuses' => self::normalizeStringList($statuses, [])]]
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function projectOptions(): array
    {
        $default = self::defaultProjectOptions();
        $stored = self::valueByKey(self::KEY_PROJECT_OPTIONS);

        if (! is_array($stored)) {
            return $default;
        }

        return [
            'categories' => self::normalizeStringList($stored['categories'] ?? [], $default['categories']),
            'service_types' => self::normalizeStringList($stored['service_types'] ?? [], $default['service_types']),
            'statuses' => self::normalizeStringList($stored['statuses'] ?? [], $default['statuses']),
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $options
     */
    public static function updateProjectOptions(array $options): void
    {
        $default = self::defaultProjectOptions();

        self::query()->updateOrCreate(
            ['key' => self::KEY_PROJECT_OPTIONS],
            [
                'value' => [
                    'categories' => self::normalizeStringList($options['categories'] ?? [], $default['categories']),
                    'service_types' => self::normalizeStringList($options['service_types'] ?? [], $default['service_types']),
                    'statuses' => self::normalizeStringList($options['statuses'] ?? [], $default['statuses']),
                ],
            ]
        );
    }

    /**
     * @return array<string, array<string, array{enabled: bool, required: bool}>>
     */
    public static function ticketFormSchema(): array
    {
        $default = self::defaultTicketFormSchema();
        $stored = self::valueByKey(self::KEY_TICKET_FORM_SCHEMA);

        if (! is_array($stored)) {
            return $default;
        }

        $normalized = $default;

        foreach (array_keys($default) as $roleKey) {
            foreach (array_keys($default[$roleKey]) as $fieldKey) {
                $enabled = (bool) ($stored[$roleKey][$fieldKey]['enabled'] ?? $default[$roleKey][$fieldKey]['enabled']);
                $required = $enabled && (bool) ($stored[$roleKey][$fieldKey]['required'] ?? $default[$roleKey][$fieldKey]['required']);

                $normalized[$roleKey][$fieldKey] = [
                    'enabled' => $enabled,
                    'required' => $required,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<string, array{enabled: bool, required: bool}>>  $schema
     */
    public static function updateTicketFormSchema(array $schema): void
    {
        $default = self::defaultTicketFormSchema();
        $normalized = $default;

        foreach (array_keys($default) as $roleKey) {
            foreach (array_keys($default[$roleKey]) as $fieldKey) {
                $enabled = (bool) ($schema[$roleKey][$fieldKey]['enabled'] ?? $default[$roleKey][$fieldKey]['enabled']);
                $required = $enabled && (bool) ($schema[$roleKey][$fieldKey]['required'] ?? $default[$roleKey][$fieldKey]['required']);

                $normalized[$roleKey][$fieldKey] = [
                    'enabled' => $enabled,
                    'required' => $required,
                ];
            }
        }

        self::query()->updateOrCreate(
            ['key' => self::KEY_TICKET_FORM_SCHEMA],
            ['value' => $normalized]
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function defaultProjectOptions(): array
    {
        return [
            'categories' => Project::DEFAULT_CATEGORIES,
            'service_types' => Project::DEFAULT_SERVICE_TYPES,
            'statuses' => Project::DEFAULT_STATUSES,
        ];
    }

    /**
     * @return array<string, array<string, array{enabled: bool, required: bool}>>
     */
    public static function defaultTicketFormSchema(): array
    {
        $roles = array_keys(self::roleLabels());
        $fieldDefinitions = self::ticketFieldDefinitions();
        $builtInFields = self::builtInTicketFieldDefinitions();
        $schema = [];

        foreach ($roles as $roleKey) {
            $schema[$roleKey] = [];

            foreach ($fieldDefinitions as $fieldKey => $fieldDefinition) {
                if ($fieldDefinition['builtin'] && isset($builtInFields[$fieldKey])) {
                    $schema[$roleKey][$fieldKey] = [
                        'enabled' => $builtInFields[$fieldKey]['default_enabled'],
                        'required' => $builtInFields[$fieldKey]['default_required'],
                    ];

                    continue;
                }

                $schema[$roleKey][$fieldKey] = [
                    'enabled' => true,
                    'required' => false,
                ];
            }
        }

        return $schema;
    }

    public static function defaultPublicLinkExpiryMinutes(): int
    {
        return 120;
    }

    public static function defaultTicketTermsText(): string
    {
        return 'By selecting this option, I agree that the requested on-call service will be billed separately, and an official invoice will be issued based on the service provided.';
    }

    /**
     * @return array<int, string>
     */
    public static function defaultTicketTermsTriggerStatuses(): array
    {
        return ['On Call'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function valueByKey(string $key): ?array
    {
        /** @var self|null $setting */
        $setting = self::query()->where('key', $key)->first();

        return is_array($setting?->value) ? $setting->value : null;
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<int, string>  $fallback
     * @return array<int, string>
     */
    private static function normalizeStringList(array $values, array $fallback): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed === '' || in_array($trimmed, $normalized, true)) {
                continue;
            }

            $normalized[] = $trimmed;
        }

        return $normalized !== [] ? $normalized : $fallback;
    }

    /**
     * @return array<string, array{label: string, type: string, default_enabled: bool, default_required: bool}>
     */
    private static function builtInTicketFieldDefinitions(): array
    {
        return [
            'title' => [
                'label' => 'Title',
                'type' => 'text',
                'default_enabled' => true,
                'default_required' => true,
            ],
            'description' => [
                'label' => 'Description',
                'type' => 'textarea',
                'default_enabled' => true,
                'default_required' => true,
            ],
            'photo' => [
                'label' => 'Photos',
                'type' => 'photo',
                'default_enabled' => true,
                'default_required' => false,
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $fields
     * @return array<int, array{key: string, label: string, type: string}>
     */
    private static function normalizeCustomFieldList(array $fields): array
    {
        $normalized = [];
        $usedKeys = array_fill_keys(array_keys(self::builtInTicketFieldDefinitions()), true);
        $allowedTypes = array_keys(self::ticketCustomFieldTypeLabels());

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $providedKey = trim((string) ($field['key'] ?? ''));
            $baseKey = self::sanitizeCustomFieldKey($providedKey !== '' ? $providedKey : $label);

            if ($baseKey === '') {
                $baseKey = 'field';
            }

            $key = $baseKey;
            $suffix = 2;

            while (isset($usedKeys[$key])) {
                $key = $baseKey.'_'.$suffix;
                $suffix++;
            }

            $type = (string) ($field['type'] ?? 'text');

            if (! in_array($type, $allowedTypes, true)) {
                $type = 'text';
            }

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
            ];

            $usedKeys[$key] = true;
        }

        return $normalized;
    }

    private static function sanitizeCustomFieldKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
