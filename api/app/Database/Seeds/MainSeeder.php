<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MainSeeder extends Seeder {
    public function run(): void {
        foreach (array_merge($this->getBaseData(), $this->getCustomData()) as $seeder) {
            $this->call($seeder);
        }
    }

    /**
     * Seeders del CORE (php-api-single-tenant).
     * Se mantienen sincronizados con el repositorio central.
     */
    private function getBaseData(): array
    {
        return [
            'App\Database\Seeds\Global\CountrySeeder',
            'App\Database\Seeds\Global\TimeZoneSeeder',
            'App\Database\Seeds\Global\CountryTimeZoneSeeder',
            'App\Database\Seeds\Global\LanguageSeeder',
            'App\Database\Seeds\Global\MetadataFieldTypeSeeder',
            'App\Database\Seeds\Global\MetadataSchemaSeeder',
            'App\Database\Seeds\Global\MetadataSchemaFieldSeeder',
            'App\Database\Seeds\Global\DocumentTypeSeeder',
            'App\Database\Seeds\Global\MetadataFieldRuleSeeder',
            'App\Database\Seeds\System\OtpActionSeeder',
            'App\Database\Seeds\System\NotificationTypeSeeder',
            'App\Database\Seeds\System\StorageLockerSeeder',
            'App\Database\Seeds\Gac\ModuleCategorySeeder',
            'App\Database\Seeds\Gac\ModuleSeeder',
            'App\Database\Seeds\Gac\RoleSeeder',
            'App\Database\Seeds\Gac\PermissionSeeder',
            'App\Database\Seeds\Gac\AdminUserSeeder',
        ];
    }

    /**
     * Seeders específicos del FORK (motcar-api).
     */
    private function getCustomData(): array
    {
        return [
            'App\Database\Seeds\Global\BranchSeeder',
            'App\Database\Seeds\Global\NoteCategorySeeder',
            'App\Database\Seeds\Finance\FrequencySeeder',
            'App\Database\Seeds\Finance\ContractTypeSeeder',
            'App\Database\Seeds\Accounting\CurrencySeeder',
            'App\Database\Seeds\Accounting\PaymentAccountCategorySeeder',
            'App\Database\Seeds\Accounting\ReceivableConceptSeeder',
        ];
    }
}
