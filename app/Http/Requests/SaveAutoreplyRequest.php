<?php

namespace App\Http\Requests;

use App\Models\AiBot;
use App\Models\Autoreply;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAutoreplyRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $autoreply = $this->route('autoreply');
        $ignoreId = $autoreply instanceof Autoreply ? $autoreply->id : null;

        return [
            'name' => ['required', 'string', 'max:191'],
            'device_id' => ['required', 'exists:devices,id'],
            'contact_tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'trigger_event' => ['required', 'in:keyword,first_chat'],
            'keyword' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('autoreplies', 'keyword')
                    ->ignore($ignoreId)
                    ->where(function ($query) {
                        return $query->where('device_id', $this->input('device_id'));
                    }),
            ],
            'type_keyword' => ['nullable', 'in:Equal,Contain'],
            'reply_when' => ['required', 'in:Group,Personal,All'],
            'type' => ['required', 'in:text,media,list,button,template,ai'],
            'status' => ['required', 'in:active,inactive'],
            'ai_bot_id' => ['nullable', 'integer', 'exists:ai_bots,id'],
            'is_quoted' => ['nullable', 'boolean'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'schedule_mode' => ['required', 'in:always,scheduled'],
            'schedule_days' => ['exclude_unless:schedule_mode,scheduled', 'required_if:schedule_mode,scheduled', 'array', 'min:1'],
            'schedule_days.*' => ['in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'schedule_start' => ['exclude_unless:schedule_mode,scheduled', 'nullable', 'required_if:schedule_mode,scheduled', 'date_format:H:i'],
            'schedule_end' => ['exclude_unless:schedule_mode,scheduled', 'nullable', 'required_if:schedule_mode,scheduled', 'date_format:H:i'],
            'message' => ['nullable', 'string'],
            'url' => ['nullable', 'string', 'max:2048'],
            'media_type' => ['nullable', 'in:image,document,video,audio'],
            'caption' => ['nullable', 'string'],
            'buttontext' => ['nullable', 'string', 'max:191'],
            'name_list' => ['nullable', 'string', 'max:191'],
            'title' => ['nullable', 'string', 'max:191'],
            'items' => ['nullable', 'array', 'min:1', 'max:5'],
            'items.*' => ['nullable', 'string', 'max:191'],
            'image' => ['nullable', 'string', 'max:2048'],
            'footer' => ['nullable', 'string', 'max:191'],
            'buttons' => ['nullable', 'array', 'min:1', 'max:5'],
            'buttons.*' => ['nullable', 'string', 'max:191'],
            'templates' => ['nullable', 'array', 'min:1', 'max:3'],
            'templates.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $triggerEvent = $this->input('trigger_event', 'keyword');
            $message = trim((string) $this->input('message'));

            if ($triggerEvent === 'keyword') {
                if (trim((string) $this->input('keyword')) === '') {
                    $validator->errors()->add('keyword', 'Keyword wajib diisi untuk trigger keyword.');
                }

                if (trim((string) $this->input('type_keyword')) === '') {
                    $validator->errors()->add('type_keyword', 'Tipe pencocokan wajib dipilih.');
                }
            }

            if (in_array($type, ['text', 'list', 'button', 'template'], true) && $message === '') {
                $validator->errors()->add('message', 'Message wajib diisi untuk tipe balasan ini.');
            }

            if ($type === 'ai') {
                $botId = $this->input('ai_bot_id');
                if (!$botId) {
                    $validator->errors()->add('ai_bot_id', 'AI Bot Profile wajib dipilih.');
                } else {
                    $bot = AiBot::find($botId);
                    if (!$bot || (int) $bot->user_id !== (int) optional($this->user())->id) {
                        $validator->errors()->add('ai_bot_id', 'AI Bot Profile tidak valid.');
                    } elseif ((int) $bot->device_id !== (int) $this->input('device_id')) {
                        $validator->errors()->add('ai_bot_id', 'AI Bot Profile harus memakai device yang sama dengan rule.');
                    }
                }
            }

            $contactTagId = $this->input('contact_tag_id');
            if ($contactTagId) {
                $tag = Tag::find($contactTagId);
                if (!$tag || (int) $tag->user_id !== (int) optional($this->user())->id) {
                    $validator->errors()->add('contact_tag_id', 'Phone Book pelanggan tidak valid.');
                }
            }

            if ($type === 'media') {
                if (trim((string) $this->input('url')) === '') {
                    $validator->errors()->add('url', 'Media URL wajib diisi.');
                }

                if (trim((string) $this->input('media_type')) === '') {
                    $validator->errors()->add('media_type', 'Media type wajib dipilih.');
                }
            }

            if ($type === 'list' && count($this->cleanEntries($this->input('items', []))) === 0) {
                $validator->errors()->add('items', 'Minimal satu item list harus diisi.');
            }

            if ($type === 'list') {
                if (trim((string) $this->input('buttontext')) === '') {
                    $validator->errors()->add('buttontext', 'Button text wajib diisi.');
                }

                if (trim((string) $this->input('name_list')) === '') {
                    $validator->errors()->add('name_list', 'Name list wajib diisi.');
                }

                if (trim((string) $this->input('title')) === '') {
                    $validator->errors()->add('title', 'Title list wajib diisi.');
                }
            }

            if ($type === 'button' && count($this->cleanEntries($this->input('buttons', []))) === 0) {
                $validator->errors()->add('buttons', 'Minimal satu button harus diisi.');
            }

            if ($type === 'template') {
                $templates = $this->cleanEntries($this->input('templates', []));
                if (count($templates) === 0) {
                    $validator->errors()->add('templates', 'Minimal satu template harus diisi.');
                }

                foreach ($templates as $index => $template) {
                    $parts = explode('|', $template);
                    if (count($parts) !== 3) {
                        $validator->errors()->add('templates.' . $index, 'Format template harus type|text|link-or-number.');
                        continue;
                    }

                    if (!in_array($parts[0], ['call', 'url'], true)) {
                        $validator->errors()->add('templates.' . $index, 'Type template hanya boleh call atau url.');
                    }
                }
            }
        });
    }

    protected function prepareForValidation()
    {
        $scheduleMode = $this->input('schedule_mode', 'always');
        $scheduleStart = trim((string) $this->input('schedule_start', ''));
        $scheduleEnd = trim((string) $this->input('schedule_end', ''));
        $triggerEvent = $this->input('trigger_event', 'keyword');
        $keyword = trim((string) $this->input('keyword', ''));

        $this->merge([
            'status' => strtolower((string) $this->input('status', 'active')),
            'is_quoted' => $this->boolean('is_quoted'),
            'priority' => $this->input('priority', 100),
            'contact_tag_id' => $this->filled('contact_tag_id') ? (int) $this->input('contact_tag_id') : null,
            'trigger_event' => $triggerEvent,
            'keyword' => $triggerEvent === 'keyword'
                ? $keyword
                : '__first_chat__:' . ($this->input('device_id') ?: 'device'),
            'type_keyword' => $triggerEvent === 'keyword'
                ? $this->input('type_keyword', 'Equal')
                : 'Equal',
            'schedule_mode' => $scheduleMode,
            'schedule_days' => $scheduleMode === 'scheduled' ? (array) $this->input('schedule_days', []) : [],
            'schedule_start' => $scheduleMode === 'scheduled' && $scheduleStart !== '' ? $scheduleStart : null,
            'schedule_end' => $scheduleMode === 'scheduled' && $scheduleEnd !== '' ? $scheduleEnd : null,
        ]);
    }

    protected function cleanEntries($entries)
    {
        return array_values(array_filter(array_map(function ($entry) {
            return trim((string) $entry);
        }, is_array($entries) ? $entries : []), function ($entry) {
            return $entry !== '';
        }));
    }
}
