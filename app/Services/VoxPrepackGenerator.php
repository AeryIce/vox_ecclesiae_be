<?php

namespace App\Services;

final class VoxPrepackGenerator
{
    public function __construct(
        private readonly OpenAIResponsesClient $client
    ) {}

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function generate(array $input): array
    {
        $dummy = $this->dummyResult($input);

        $enabled = (bool) config('openai.enabled', true);
        $apiKey = (string) config('openai.api_key', '');

        if (!$enabled || $apiKey === '') {
            $dummy['ai'] = ['used' => false, 'reason' => 'OPENAI disabled or key missing'];
            return $dummy;
        }

        try {
            $schema = $this->prepackSchema();
            $instructions = $this->buildInstructions($input);
            $userInput = $this->buildUserInput($input);

            $ai = $this->client->createJsonSchema(
                instructions: $instructions,
                input: $userInput,
                schema: $schema,
                schemaName: 'vox_prepack_v1'
            );

            /** @var array<string,mixed> $prepack */
            $prepack = $ai['json'];

            $markdown = $this->buildMarkdown($prepack);

            return [
                'ok' => true,
                'pack_type' => 'pre',
                'version' => 'v1_ai',
                'ai' => [
                    'used' => true,
                    'model' => $ai['model'],
                ],
                'input' => $input,
                'prepack' => $prepack,
                'markdown' => $markdown,
            ];
        } catch (\Throwable $e) {
            // NO 500. Fallback dummy + kasih error biar kebaca di FE.
            $dummy['ai'] = [
                'used' => false,
                'error' => $e->getMessage(),
            ];
            return $dummy;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function dummyResult(array $validated): array
    {
        $workingTitle = "({$validated['audience']}) {$validated['topic']} — Obrolan {$validated['purpose']}";
        $openingHook = "Bayangin kalau kita sudah sibuk ke mana-mana, tapi hati tetap kosong. "
            . "Di episode ini, kita ngobrol soal “{$validated['topic']}” bareng {$validated['salutation']} "
            . "({$validated['guest_role_context']}) — supaya iman nyambung lagi ke hidup harian.";

        $rundown = [
            ['segment' => 'Intro & konteks', 'minutes' => 2, 'goal' => 'Set tone, kenalin topik dan narasumber.'],
            ['segment' => 'Cerita nyata / pemantik', 'minutes' => 6, 'goal' => 'Masuk realita, problem yang sering kejadian.'],
            ['segment' => 'Insight inti Gereja / iman', 'minutes' => 10, 'goal' => 'Benang merah: apa yang Tuhan mau kita pahami.'],
            ['segment' => 'Aplikasi praktis', 'minutes' => 8, 'goal' => 'Langkah kecil yang bisa dilakukan minggu ini.'],
            ['segment' => 'Closing & ajakan refleksi', 'minutes' => 2, 'goal' => 'Ringkas, doa singkat/CTA elegan.'],
        ];

        $questions = [
            [
                'q' => "Kalau {$validated['topic']} itu dirangkum 1 kalimat, apa intinya?",
                'followups' => [
                    "Boleh kasih contoh paling dekat di hidup sehari-hari?",
                    "Apa kesalahan paling umum yang sering orang lakukan di sini?",
                ],
            ],
            [
                'q' => "Menurut {$validated['salutation']}, kenapa topik ini penting buat {$validated['audience']}?",
                'followups' => [
                    "Kalau orang menganggap ini sepele, biasanya dampaknya apa?",
                    "Apa tanda-tanda kita mulai melenceng tanpa sadar?",
                ],
            ],
            [
                'q' => "Dari poin wajib pertama: {$validated['must_points'][0]} — ini mau dibawa ke arah apa?",
                'followups' => [
                    "Kalau dijelasin ke orang awam, analoginya apa?",
                    "Apa 1 latihan kecil yang bisa dicoba minggu ini?",
                ],
            ],
            [
                'q' => "Apa momen yang biasanya jadi turning point dalam proses rohani terkait topik ini?",
                'followups' => [
                    "Ada contoh pengalaman nyata (tanpa sebut nama) yang bisa jadi pelajaran?",
                    "Kalau lagi jatuh/mandek, mulai lagi dari mana?",
                ],
            ],
            [
                'q' => "Kalau kita mau tutup episode ini dengan 1 kalimat benang merah, kalimatnya apa?",
                'followups' => [
                    "Bikin versi singkatnya yang enak jadi pinned comment dong.",
                ],
            ],
        ];

        $momentTargets = [
            ['label' => 'One-liner benang merah', 'why' => 'Bahan paling gampang jadi Shorts/teks overlay.', 'where' => 'Segmen Insight inti'],
            ['label' => 'Step praktis 3 langkah', 'why' => 'Audiens suka yang bisa langsung dipraktikkan.', 'where' => 'Segmen Aplikasi praktis'],
            ['label' => 'Cerita nyata singkat', 'why' => 'Story = emosi kebuka, retention naik.', 'where' => 'Segmen Cerita/pemantik'],
        ];

        $closingCta = "Terima kasih sudah menemani. Kalau episode ini menguatkan, "
            . "boleh share ke 1 orang yang kamu sayangi. Tuhan memberkati.";

        $prepack = [
            'working_title' => $workingTitle,
            'opening_hook' => $openingHook,
            'rundown' => $rundown,
            'questions' => $questions,
            'moment_targets' => $momentTargets,
            'closing_cta' => $closingCta,
        ];

        return [
            'ok' => true,
            'pack_type' => 'pre',
            'version' => 'v1_dummy',
            'ai' => ['used' => false],
            'input' => $validated,
            'prepack' => $prepack,
            'markdown' => $this->buildMarkdown($prepack),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function prepackSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['working_title', 'opening_hook', 'rundown', 'questions', 'moment_targets', 'closing_cta'],
            'properties' => [
                'working_title' => ['type' => 'string', 'minLength' => 3],
                'opening_hook' => ['type' => 'string', 'minLength' => 10],
                'rundown' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 8,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['segment', 'minutes', 'goal'],
                        'properties' => [
                            'segment' => ['type' => 'string', 'minLength' => 2],
                            'minutes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 60],
                            'goal' => ['type' => 'string', 'minLength' => 3],
                        ],
                    ],
                ],
                'questions' => [
                    'type' => 'array',
                    'minItems' => 4,
                    'maxItems' => 10,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['q', 'followups'],
                        'properties' => [
                            'q' => ['type' => 'string', 'minLength' => 5],
                            'followups' => [
                                'type' => 'array',
                                'minItems' => 1,
                                'maxItems' => 4,
                                'items' => ['type' => 'string', 'minLength' => 3],
                            ],
                        ],
                    ],
                ],
                'moment_targets' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'maxItems' => 6,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['label', 'why', 'where'],
                        'properties' => [
                            'label' => ['type' => 'string', 'minLength' => 2],
                            'why' => ['type' => 'string', 'minLength' => 5],
                            'where' => ['type' => 'string', 'minLength' => 2],
                        ],
                    ],
                ],
                'closing_cta' => ['type' => 'string', 'minLength' => 5],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $input
     */
    private function buildInstructions(array $input): string
    {
        $formality = (string) ($input['formality'] ?? 'formal_ringan');
        $tone = match ($formality) {
            'sangat_formal' => 'sangat sopan, formal, tenang',
            'hangat_ramah' => 'hangat, ramah, membumi',
            default => 'formal ringan, hangat, sopan',
        };

        return implode("\n", [
            "Kamu adalah asisten Komsos Gereja bernama Vox Ecclesiae.",
            "Tugas: buat PRE Pack untuk produksi video/podcast rohani.",
            "Bahasa: Indonesia.",
            "Tone: {$tone}.",
            "Aturan: jangan bahas politik praktis; jangan sebut nama orang nyata; jaga sensitivitas; fokus membangun iman dan aplikasi praktis.",
            "Wajib patuh schema JSON yang diberikan (strict).",
        ]);
    }

    /**
     * @param array<string,mixed> $input
     */
    private function buildUserInput(array $input): string
    {
        $mustPoints = is_array($input['must_points'] ?? null) ? $input['must_points'] : [];
        $mustPointsText = implode("; ", array_map(fn ($p) => (string) $p, $mustPoints));
        $constraints = (string) ($input['sensitive_constraints'] ?? '');

        return implode("\n", [
            "Topik: {$input['topic']}",
            "Tujuan: {$input['purpose']}",
            "Audiens: {$input['audience']}",
            "Durasi: {$input['duration_minutes']} menit",
            "Format: {$input['format']}",
            "Konteks narasumber: {$input['guest_role_context']}",
            "Sapaan: {$input['salutation']}",
            "Poin wajib: {$mustPointsText}",
            $constraints !== '' ? "Batasan sensitif: {$constraints}" : "Batasan sensitif: (tidak ada)",
            "",
            "Outputkan PRE Pack yang praktis dan siap dipakai host untuk wawancara.",
        ]);
    }

    /**
     * @param array<string,mixed> $prepack
     */
    private function buildMarkdown(array $prepack): string
    {
        $rundownLines = [];
        if (isset($prepack['rundown']) && is_array($prepack['rundown'])) {
            foreach ($prepack['rundown'] as $s) {
                if (!is_array($s)) continue;
                $rundownLines[] = "- **" . (string) ($s['segment'] ?? '') . "** (" . (string) ($s['minutes'] ?? '') . "m): " . (string) ($s['goal'] ?? '');
            }
        }

        $questionLines = [];
        if (isset($prepack['questions']) && is_array($prepack['questions'])) {
            $i = 1;
            foreach ($prepack['questions'] as $qItem) {
                if (!is_array($qItem)) continue;
                $questionLines[] = "{$i}. " . (string) ($qItem['q'] ?? '');
                $followups = $qItem['followups'] ?? [];
                if (is_array($followups)) {
                    foreach ($followups as $f) {
                        $questionLines[] = "   - follow-up: " . (string) $f;
                    }
                }
                $i++;
            }
        }

        $momentLines = [];
        if (isset($prepack['moment_targets']) && is_array($prepack['moment_targets'])) {
            foreach ($prepack['moment_targets'] as $m) {
                if (!is_array($m)) continue;
                $momentLines[] = "- **" . (string) ($m['label'] ?? '') . "** (" . (string) ($m['where'] ?? '') . "): " . (string) ($m['why'] ?? '');
            }
        }

        return implode("\n", [
            "## PRE-PRODUCTION PACK — Vox Ecclesiae",
            "",
            "### 1) Judul kerja episode",
            "- " . (string) ($prepack['working_title'] ?? ''),
            "",
            "### 2) Opening Hook (30–60 detik)",
            (string) ($prepack['opening_hook'] ?? ''),
            "",
            "### 3) Rundown segmen",
            ...$rundownLines,
            "",
            "### 4) Pertanyaan inti + follow-up",
            ...$questionLines,
            "",
            "### 5) Moment Target",
            ...$momentLines,
            "",
            "### 6) Closing + CTA elegan",
            (string) ($prepack['closing_cta'] ?? ''),
        ]);
    }
}