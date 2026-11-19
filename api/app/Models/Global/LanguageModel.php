<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class LanguageModel extends BaseModel {
    protected $table = 'glb_language';

    private const LANGUAGES_CACHE_KEY = 'db_lng_list';
    private const LANGUAGES_CACHE_TTL = 3600;

    protected $allowedFields = [
        'code', 'name', 'is_disabled',
    ];

    protected $validationRules = [
        'code'        => 'required|string|max_length[2]|is_unique[glb_language.code,id,{id}]',
        'name'        => 'required|valid_i18n[min_length:1,max_length:50]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['name'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];

    /**
     * Idiomas indexados por id (cacheados).
     * Prefiere `service('cache')` y solo consulta la base de datos en miss.
     * Con `$reset = true` fuerza la recarga (borra la caché y la reconstruye).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByCache(bool $reset = false): array {
        $cache = service('cache');
        $cacheKey = self::LANGUAGES_CACHE_KEY;

        if ($reset) {
            $cache->delete($cacheKey);
        }

        $languages = $cache->get($cacheKey);
        if (!is_array($languages)) {
            $rows = $this->select('id, code, is_disabled')
                ->where('deleted_at', null)
                ->findAll();

            $languages = [];
            foreach ($rows as $row) {
                $languages[(int) $row['id']] = [
                    'code'        => $row['code'],
                    'is_disabled' => $row['is_disabled'],
                ];
            }

            $cache->save($cacheKey, $languages, self::LANGUAGES_CACHE_TTL);
        }

        return $languages;
    }

    /**
     * Invalida la caché de idiomas.
     */
    public function removeByCache(): void {
        service('cache')->delete(self::LANGUAGES_CACHE_KEY);
    }

    /**
     * Resuelve el locale a partir de language_id, fallback provisto o configuración global.
     */
    public function resolveLocale(?int $languageId, ?string $fallbackLocale = null): string {
        if (!empty($languageId)) {
            $languages = $this->getByCache();
            if (!empty($languages[(int) $languageId]['code']) && ($languages[(int) $languageId]['is_disabled'] ?? '0') === '0') {
                return (string) $languages[(int) $languageId]['code'];
            }
        }

        return $fallbackLocale ?: (config('App')->defaultLocale ?? 'es');
    }
}

