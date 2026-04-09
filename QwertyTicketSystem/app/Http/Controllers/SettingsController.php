<?php

namespace App\Http\Controllers;

use App\Models\ServiceDeskSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        return view('settings.index', [
            'currentUser' => $request->user(),
            'projectOptions' => ServiceDeskSetting::projectOptions(),
            'ticketSchema' => ServiceDeskSetting::ticketFormSchema(),
            'roleLabels' => ServiceDeskSetting::roleLabels(),
            'ticketFieldDefinitions' => ServiceDeskSetting::ticketFieldDefinitions(),
            'ticketCustomFields' => ServiceDeskSetting::ticketCustomFields(),
            'ticketCustomFieldTypeLabels' => ServiceDeskSetting::ticketCustomFieldTypeLabels(),
            'publicLinkExpiryMinutes' => ServiceDeskSetting::publicLinkExpiryMinutes(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_categories' => ['required', 'string'],
            'project_service_types' => ['required', 'string'],
            'project_statuses' => ['required', 'string'],
            'public_link_expiry_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*.key' => ['nullable', 'string', 'max:80'],
            'custom_fields.*.label' => ['nullable', 'string', 'max:120'],
            'custom_fields.*.type' => ['nullable', Rule::in(array_keys(ServiceDeskSetting::ticketCustomFieldTypeLabels()))],
        ]);

        ServiceDeskSetting::updateProjectOptions([
            'categories' => $this->linesFromText($validated['project_categories']),
            'service_types' => $this->linesFromText($validated['project_service_types']),
            'statuses' => $this->linesFromText($validated['project_statuses']),
        ]);

        $customFields = [];

        foreach (($validated['custom_fields'] ?? []) as $field) {
            if (! is_array($field)) {
                continue;
            }

            $customFields[] = [
                'key' => (string) ($field['key'] ?? ''),
                'label' => trim((string) ($field['label'] ?? '')),
                'type' => (string) ($field['type'] ?? 'text'),
            ];
        }

        ServiceDeskSetting::updateTicketCustomFields($customFields);

        $schema = ServiceDeskSetting::defaultTicketFormSchema();
        $fieldKeys = array_keys(ServiceDeskSetting::ticketFieldDefinitions());

        foreach (array_keys(ServiceDeskSetting::roleLabels()) as $roleKey) {
            foreach ($fieldKeys as $fieldKey) {
                $enabledPath = "schema.{$roleKey}.{$fieldKey}.enabled";
                $requiredPath = "schema.{$roleKey}.{$fieldKey}.required";
                $defaultEnabled = (bool) ($schema[$roleKey][$fieldKey]['enabled'] ?? false);
                $defaultRequired = (bool) ($schema[$roleKey][$fieldKey]['required'] ?? false);

                $enabled = $request->has($enabledPath)
                    ? $request->boolean($enabledPath)
                    : $defaultEnabled;
                $required = $enabled && (
                    $request->has($requiredPath)
                        ? $request->boolean($requiredPath)
                        : $defaultRequired
                );

                $schema[$roleKey][$fieldKey] = [
                    'enabled' => $enabled,
                    'required' => $required,
                ];
            }
        }

        ServiceDeskSetting::updateTicketFormSchema($schema);
        ServiceDeskSetting::updatePublicLinkExpiryMinutes((int) $validated['public_link_expiry_minutes']);

        return back()->with('status', 'Settings updated.');
    }

    /**
     * @return array<int, string>
     */
    private function linesFromText(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $normalized = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || in_array($trimmed, $normalized, true)) {
                continue;
            }

            $normalized[] = $trimmed;
        }

        return $normalized;
    }
}
