<?php

/**
 * Rutas personalizadas del Fork Motcar.
 *
 * Se cargan DESPUÉS de Core.php.
 */

// ════════════════════════════════════════════════════════════
//  MOTCAR — FASE 1: Catálogos Globales, Tesorería y Financiamiento
// ════════════════════════════════════════════════════════════

// ─────────────────────────────────────────
//  1. CATÁLOGOS GLOBALES
// ─────────────────────────────────────────
$routes->group('v1/catalogs', ['namespace' => 'App\Controllers\V1\Catalogs', 'filter' => ['auth']], function ($routes) {

    // Personas (Directorio unificado)
    $routes->group('persons', ['namespace' => 'App\Controllers\V1\Catalogs\Persons'], function ($routes) {
        $routes->post('',         'Home::create',     ['filter' => 'auth:module_code=glb_persons,only_user']);
        $routes->get('',          'Home::index',      ['filter' => 'auth:module_code=glb_persons']);
        $routes->get('(:num)',    'Home::show/$1',    ['filter' => 'auth:module_code=glb_persons']);
        // Unitarios
        $routes->put('(:num)',    'Home::update/$1',  ['filter' => 'auth:module_code=glb_persons,only_user']);
        $routes->delete('(:num)', 'Home::delete/$1',  ['filter' => 'auth:module_code=glb_persons,only_user']);
        $routes->patch('(:num)',  'Home::restore/$1', ['filter' => 'auth:module_code=glb_persons,only_user']);
        // Masivos
        $routes->put('',          'Home::update',     ['filter' => 'auth:module_code=glb_persons,only_user']);
        $routes->delete('',       'Home::delete',     ['filter' => 'auth:module_code=glb_persons,only_user']);
        $routes->patch('',        'Home::restore',    ['filter' => 'auth:module_code=glb_persons,only_user']);
    });
});

// ─────────────────────────────────────────
//  2. TESORERÍA / ACCOUNTING GLOBAL
// ─────────────────────────────────────────
$routes->group('v1/accounting', ['namespace' => 'App\Controllers\V1\Accounting'], function ($routes) {

    // Monedas y sus Tasas de Cambio
    $routes->group('currencies', ['namespace' => 'App\Controllers\V1\Accounting\Currencies'], function ($routes) {
        $routes->post('',         'Home::create',     ['filter' => 'auth:module_code=acc_currencies,only_user']);
        $routes->get('',          'Home::index',      ['filter' => 'auth:module_code=acc_currencies']);
        $routes->get('(:num)',    'Home::show/$1',    ['filter' => 'auth:module_code=acc_currencies']);
        // Unitarios
        $routes->put('(:num)',    'Home::update/$1',  ['filter' => 'auth:module_code=acc_currencies,only_user']);
        $routes->delete('(:num)', 'Home::delete/$1',  ['filter' => 'auth:module_code=acc_currencies,only_user']);
        $routes->patch('(:num)',  'Home::restore/$1', ['filter' => 'auth:module_code=acc_currencies,only_user']);
        // Masivos
        $routes->put('',          'Home::update',     ['filter' => 'auth:module_code=acc_currencies,only_user']);
        $routes->delete('',       'Home::delete',     ['filter' => 'auth:module_code=acc_currencies,only_user']);
        $routes->patch('',        'Home::restore',    ['filter' => 'auth:module_code=acc_currencies,only_user']);

        // Tasas de cambio de la moneda
        $routes->get('(:num)/exchange-rates/current',   'ExchangeRates::current/$1',     ['filter' => 'auth:module_code=acc_exchange_rates']);
        $routes->post('(:num)/exchange-rates',          'ExchangeRates::create/$1',      ['filter' => 'auth:module_code=acc_exchange_rates,only_user']);
        $routes->get('(:num)/exchange-rates',           'ExchangeRates::index/$1',       ['filter' => 'auth:module_code=acc_exchange_rates']);
        $routes->get('(:num)/exchange-rates/(:num)',    'ExchangeRates::show/$1/$2',     ['filter' => 'auth:module_code=acc_exchange_rates']);
        // Unitarios
        $routes->put('(:num)/exchange-rates/(:num)',    'ExchangeRates::update/$1/$2',   ['filter' => 'auth:module_code=acc_exchange_rates,only_user']);
        $routes->delete('(:num)/exchange-rates/(:num)', 'ExchangeRates::delete/$1/$2',   ['filter' => 'auth:module_code=acc_exchange_rates,only_user']);
        $routes->patch('(:num)/exchange-rates/(:num)',  'ExchangeRates::restore/$1/$2',  ['filter' => 'auth:module_code=acc_exchange_rates,only_user']);
        // Masivos
        $routes->put('(:num)/exchange-rates',           'ExchangeRates::update/$1',      ['filter' => 'auth:module_code=acc_exchange_rates,only_user']);
        $routes->delete('(:num)/exchange-rates',        'ExchangeRates::delete/$1',      ['filter' => 'auth:module_code=acc_exchange_rates,only_user']);
        $routes->patch('(:num)/exchange-rates',         'ExchangeRates::restore/$1',     ['filter' => 'auth:module_code=acc_exchange_rates,only_user']);
    });

    // Canales de Pago (Categorías y Cuentas Receptoras)
    $routes->group('payments', ['namespace' => 'App\Controllers\V1\Accounting\Payments'], function ($routes) {
        // Categorías de cuentas de pago
        $routes->group('categories', ['namespace' => 'App\Controllers\V1\Accounting\Payments\Categories'], function ($routes) {
            $routes->post('',         'Home::create',     ['filter' => 'auth:module_code=acc_payment_account_categories,only_user']);
            $routes->get('',          'Home::index',      ['filter' => 'auth:module_code=acc_payment_account_categories']);
            $routes->get('(:num)',    'Home::show/$1',    ['filter' => 'auth:module_code=acc_payment_account_categories']);
            // Unitarios
            $routes->put('(:num)',    'Home::update/$1',  ['filter' => 'auth:module_code=acc_payment_account_categories,only_user']);
            $routes->delete('(:num)', 'Home::delete/$1',  ['filter' => 'auth:module_code=acc_payment_account_categories,only_user']);
            $routes->patch('(:num)',  'Home::restore/$1', ['filter' => 'auth:module_code=acc_payment_account_categories,only_user']);
            // Masivos
            $routes->put('',          'Home::update',     ['filter' => 'auth:module_code=acc_payment_account_categories,only_user']);
            $routes->delete('',       'Home::delete',     ['filter' => 'auth:module_code=acc_payment_account_categories,only_user']);
            $routes->patch('',        'Home::restore',    ['filter' => 'auth:module_code=acc_payment_account_categories,only_user']);

            // Esquema de metadatos controlado 1:1 por categoría
            $routes->post('(:num)/schema', 'Home::createSchema/$1', ['filter' => 'auth:module_code=acc_payment_account_categories,only_user']);

            // Cuentas de pago subordinadas a la categoría
            $routes->post('(:num)/accounts',          'Accounts::create/$1',      ['filter' => 'auth:module_code=acc_payment_accounts,only_user']);
            $routes->get('(:num)/accounts',           'Accounts::index/$1',       ['filter' => 'auth:module_code=acc_payment_accounts']);
            $routes->get('(:num)/accounts/(:num)',    'Accounts::show/$1/$2',     ['filter' => 'auth:module_code=acc_payment_accounts']);
            // Unitarios
            $routes->put('(:num)/accounts/(:num)',    'Accounts::update/$1/$2',   ['filter' => 'auth:module_code=acc_payment_accounts,only_user']);
            $routes->delete('(:num)/accounts/(:num)', 'Accounts::delete/$1/$2',   ['filter' => 'auth:module_code=acc_payment_accounts,only_user']);
            $routes->patch('(:num)/accounts/(:num)',  'Accounts::restore/$1/$2',  ['filter' => 'auth:module_code=acc_payment_accounts,only_user']);
            // Masivos
            $routes->put('(:num)/accounts',           'Accounts::update/$1',      ['filter' => 'auth:module_code=acc_payment_accounts,only_user']);
            $routes->delete('(:num)/accounts',        'Accounts::delete/$1',      ['filter' => 'auth:module_code=acc_payment_accounts,only_user']);
            $routes->patch('(:num)/accounts',         'Accounts::restore/$1',     ['filter' => 'auth:module_code=acc_payment_accounts,only_user']);
        });

        // Listado transversal de cuentas de pago (todas las categorías)
        $routes->get('accounts', 'Categories\Accounts::index', ['filter' => 'auth:module_code=acc_payment_accounts']);
    });

    // Cartera / Conceptos de Cobro
    $routes->group('receivables', ['namespace' => 'App\Controllers\V1\Accounting\Receivables'], function ($routes) {
        $routes->group('concepts', function ($routes) {
            $routes->post('',         'Concepts::create',     ['filter' => 'auth:module_code=acc_receivable_concepts,only_user']);
            $routes->get('',          'Concepts::index',      ['filter' => 'auth:module_code=acc_receivable_concepts']);
            $routes->get('(:num)',    'Concepts::show/$1',    ['filter' => 'auth:module_code=acc_receivable_concepts']);
            // Unitarios
            $routes->put('(:num)',    'Concepts::update/$1',  ['filter' => 'auth:module_code=acc_receivable_concepts,only_user']);
            $routes->delete('(:num)', 'Concepts::delete/$1',  ['filter' => 'auth:module_code=acc_receivable_concepts,only_user']);
            $routes->patch('(:num)',  'Concepts::restore/$1', ['filter' => 'auth:module_code=acc_receivable_concepts,only_user']);
            // Masivos
            $routes->put('',          'Concepts::update',     ['filter' => 'auth:module_code=acc_receivable_concepts,only_user']);
            $routes->delete('',       'Concepts::delete',     ['filter' => 'auth:module_code=acc_receivable_concepts,only_user']);
            $routes->patch('',        'Concepts::restore',    ['filter' => 'auth:module_code=acc_receivable_concepts,only_user']);
        });
    });
});

// ─────────────────────────────────────────
//  3. FINANCIAMIENTO / FINANCING GLOBAL
// ─────────────────────────────────────────
$routes->group('v1/financing', ['namespace' => 'App\Controllers\V1\Financing', 'filter' => ['auth']], function ($routes) {

    // Contratos (Tipos de contrato) — solo lectura, sin módulos
    $routes->group('contracts', ['namespace' => 'App\Controllers\V1\Financing\Contracts'], function ($routes) {
        $routes->group('types', function ($routes) {
            $routes->get('',       'Types::index');
            $routes->get('(:num)', 'Types::show/$1');
        });
    });

    // Frecuencias — solo lectura, libre
    $routes->group('frequencies', ['namespace' => 'App\Controllers\V1\Financing\Frequencies'], function ($routes) {
        $routes->get('',       'Home::index');
        $routes->get('(:num)', 'Home::show/$1');
    });

    // Notas (Categorías de notas CRM)
    $routes->group('notes', ['namespace' => 'App\Controllers\V1\Financing\Notes'], function ($routes) {
        $routes->group('categories', function ($routes) {
            $routes->post('',         'Categories::create',     ['filter' => 'auth:module_code=glb_note_categories,only_user']);
            $routes->get('',          'Categories::index',      ['filter' => 'auth:module_code=glb_note_categories']);
            $routes->get('(:num)',    'Categories::show/$1',    ['filter' => 'auth:module_code=glb_note_categories']);
            // Unitarios
            $routes->put('(:num)',    'Categories::update/$1',  ['filter' => 'auth:module_code=glb_note_categories,only_user']);
            $routes->delete('(:num)', 'Categories::delete/$1',  ['filter' => 'auth:module_code=glb_note_categories,only_user']);
            $routes->patch('(:num)',  'Categories::restore/$1', ['filter' => 'auth:module_code=glb_note_categories,only_user']);
            // Masivos
            $routes->put('',          'Categories::update',     ['filter' => 'auth:module_code=glb_note_categories,only_user']);
            $routes->delete('',       'Categories::delete',     ['filter' => 'auth:module_code=glb_note_categories,only_user']);
            $routes->patch('',        'Categories::restore',    ['filter' => 'auth:module_code=glb_note_categories,only_user']);
        });
    });
});

// ─────────────────────────────────────────
//  4. OPERACIONES POR SUCURSAL (FASE 2)
// ─────────────────────────────────────────
$routes->group('v1/branches/(:num)', ['namespace' => 'App\Controllers\V1\Branches'], function ($routes) {

    // 4.1 FINANCIAMIENTO (Grupos SAM, Participantes y Financiamientos Individuales)
    $routes->group('financing', ['namespace' => 'App\Controllers\V1\Branches\Financing'], function ($routes) {
        $routes->group('groups', ['namespace' => 'App\Controllers\V1\Branches\Financing\Groups'], function ($routes) {
            $routes->post('',                                   'Home::create/$1',                  ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups,only_user']);
            $routes->get('',                                    'Home::index/$1',                   ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups']);
            $routes->get('(:num)',                              'Home::show/$1/$2',                 ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups']);
            $routes->put('(:num)',                              'Home::update/$1/$2',               ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups,only_user']);
            $routes->delete('(:num)',                           'Home::delete/$1/$2',               ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups,only_user']);
            $routes->patch('(:num)',                            'Home::restore/$1/$2',              ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups,only_user']);
            $routes->put('',                                    'Home::update/$1',                  ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups,only_user']);
            $routes->delete('',                                 'Home::delete/$1',                  ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups,only_user']);
            $routes->patch('',                                  'Home::restore/$1',                 ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups,only_user']);

            // Operaciones especiales de grupo
            $routes->post('(:num)/generate-receivables',        'Home::generateReceivables/$1/$2',  ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups,only_user']);
            $routes->get('(:num)/qualified-participants',       'Home::qualifiedParticipants/$1/$2', ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups']);

            // Reportes del grupo SAM
            $routes->group('(:num)/reports', function ($routes) {
                $routes->get('summary', 'Reports::summary/$1/$2', ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups']);
            });

            // Participantes del grupo SAM
            $routes->group('(:num)/participants', ['namespace' => 'App\Controllers\V1\Branches\Financing\Groups\Participants'], function ($routes) {
                $routes->post('',         'Home::create/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=fnc_san_participants,only_user']);
                $routes->get('',          'Home::index/$1/$2',      ['filter' => 'auth:scope=uri|3,module_code=fnc_san_participants']);
                $routes->get('(:num)',    'Home::show/$1/$2/$3',    ['filter' => 'auth:scope=uri|3,module_code=fnc_san_participants']);
                $routes->put('(:num)',    'Home::update/$1/$2/$3',  ['filter' => 'auth:scope=uri|3,module_code=fnc_san_participants,only_user']);
                $routes->delete('(:num)', 'Home::delete/$1/$2/$3',  ['filter' => 'auth:scope=uri|3,module_code=fnc_san_participants,only_user']);
                $routes->patch('(:num)',  'Home::restore/$1/$2/$3', ['filter' => 'auth:scope=uri|3,module_code=fnc_san_participants,only_user']);
                $routes->put('',          'Home::update/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=fnc_san_participants,only_user']);
                $routes->delete('',       'Home::delete/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=fnc_san_participants,only_user']);
                $routes->patch('',        'Home::restore/$1/$2',    ['filter' => 'auth:scope=uri|3,module_code=fnc_san_participants,only_user']);
                $routes->post('(:num)/generate-receivables', 'Home::generateReceivables/$1/$2/$3', ['filter' => 'auth:scope=uri|3,module_code=fnc_san_participants,only_user']);

                // Reportes individuales del participante
                $routes->group('(:num)/reports', function ($routes) {
                    $routes->get('statement', 'Reports::statement/$1/$2/$3', ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups']);
                });

                // Bitácora / Notas operativas del participante
                $routes->group('(:num)/notes', function ($routes) {
                    $routes->post('',         'Notes::create/$1/$2/$3',     ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
                    $routes->get('',          'Notes::index/$1/$2/$3',      ['filter' => 'auth:scope=uri|3,module_code=glb_notes']);
                    $routes->get('(:num)',    'Notes::show/$1/$2/$3/$4',    ['filter' => 'auth:scope=uri|3,module_code=glb_notes']);
                    $routes->put('(:num)',    'Notes::update/$1/$2/$3/$4',  ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
                    $routes->delete('(:num)', 'Notes::delete/$1/$2/$3/$4',  ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
                    $routes->patch('(:num)',  'Notes::restore/$1/$2/$3/$4', ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
                    $routes->put('',          'Notes::update/$1/$2/$3',     ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
                    $routes->delete('',       'Notes::delete/$1/$2/$3',     ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
                    $routes->patch('',        'Notes::restore/$1/$2/$3',    ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
                });

                // Contrato formalizado del participante (1:1)
                $routes->group('(:num)/contract', function ($routes) {
                    $routes->get('',     'Contract::show/$1/$2/$3',   ['filter' => 'auth:scope=uri|3,module_code=fnc_contract_documents']);
                    $routes->post('',    'Contract::create/$1/$2/$3', ['filter' => 'auth:scope=uri|3,module_code=fnc_contract_documents,only_user']);
                    $routes->get('pdf',  'Contract::pdf/$1/$2/$3',    ['filter' => 'auth:scope=uri|3,module_code=fnc_contract_documents']);
                    $routes->put('sign', 'Contract::sign/$1/$2/$3',   ['filter' => 'auth:scope=uri|3,module_code=fnc_contract_documents,only_user']);
                });
            });
        });

        // Reportes transversales de financiamiento por sucursal
        $routes->group('reports', ['namespace' => 'App\Controllers\V1\Branches\Financing\Reports'], function ($routes) {
            $routes->get('summary',             'Home::summary/$1',           ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups']);
            $routes->get('defaulters',          'Home::defaulters/$1',        ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups']);
            $routes->get('collection-calendar',   'Home::calendar/$1',             ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups']);
            $routes->get('awarded-benefits',      'Home::awardedBenefits/$1',      ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups']);
            $routes->get('participant-statement', 'Home::participantStatement/$1', ['filter' => 'auth:scope=uri|3,module_code=fnc_san_groups']);
        });

        // Financiamientos individuales
        $routes->post('',         'Home::create/$1',     ['filter' => 'auth:scope=uri|3,module_code=fnc_financings,only_user']);
        $routes->get('',          'Home::index/$1',      ['filter' => 'auth:scope=uri|3,module_code=fnc_financings']);
        $routes->get('(:num)',    'Home::show/$1/$2',    ['filter' => 'auth:scope=uri|3,module_code=fnc_financings']);
        $routes->put('(:num)',    'Home::update/$1/$2',  ['filter' => 'auth:scope=uri|3,module_code=fnc_financings,only_user']);
        $routes->delete('(:num)', 'Home::delete/$1/$2',  ['filter' => 'auth:scope=uri|3,module_code=fnc_financings,only_user']);
        $routes->patch('(:num)',  'Home::restore/$1/$2', ['filter' => 'auth:scope=uri|3,module_code=fnc_financings,only_user']);
        $routes->put('',          'Home::update/$1',     ['filter' => 'auth:scope=uri|3,module_code=fnc_financings,only_user']);
        $routes->delete('',       'Home::delete/$1',     ['filter' => 'auth:scope=uri|3,module_code=fnc_financings,only_user']);
        $routes->patch('',        'Home::restore/$1',    ['filter' => 'auth:scope=uri|3,module_code=fnc_financings,only_user']);
        $routes->post('(:num)/generate-receivables', 'Home::generateReceivables/$1/$2', ['filter' => 'auth:scope=uri|3,module_code=fnc_financings,only_user']);
        $routes->get('(:num)/reports/statement',     'Home::statement/$1/$2',           ['filter' => 'auth:scope=uri|3,module_code=fnc_financings']);

        // Bitácora / Notas operativas del financiamiento
        $routes->group('(:num)/notes', function ($routes) {
            $routes->post('',         'Notes::create/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
            $routes->get('',          'Notes::index/$1/$2',      ['filter' => 'auth:scope=uri|3,module_code=glb_notes']);
            $routes->get('(:num)',    'Notes::show/$1/$2/$3',    ['filter' => 'auth:scope=uri|3,module_code=glb_notes']);
            $routes->put('(:num)',    'Notes::update/$1/$2/$3',  ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
            $routes->delete('(:num)', 'Notes::delete/$1/$2/$3',  ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
            $routes->patch('(:num)',  'Notes::restore/$1/$2/$3', ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
            $routes->put('',          'Notes::update/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
            $routes->delete('',       'Notes::delete/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
            $routes->patch('',        'Notes::restore/$1/$2',    ['filter' => 'auth:scope=uri|3,module_code=glb_notes,only_user']);
        });

        // Contrato formalizado del financiamiento (1:1)
        $routes->group('(:num)/contract', function ($routes) {
            $routes->get('',     'Contract::show/$1/$2',   ['filter' => 'auth:scope=uri|3,module_code=fnc_contract_documents']);
            $routes->post('',    'Contract::create/$1/$2', ['filter' => 'auth:scope=uri|3,module_code=fnc_contract_documents,only_user']);
            $routes->get('pdf',  'Contract::pdf/$1/$2',    ['filter' => 'auth:scope=uri|3,module_code=fnc_contract_documents']);
            $routes->put('sign', 'Contract::sign/$1/$2',   ['filter' => 'auth:scope=uri|3,module_code=fnc_contract_documents,only_user']);
        });
    });

    // 4.2 CONTABILIDAD Y COBRANZAS (Cuentas por Cobrar y Recaudación de Caja)
    $routes->group('accounting', ['namespace' => 'App\Controllers\V1\Branches\Accounting'], function ($routes) {
        // Cuentas por Cobrar
        $routes->group('receivables', ['namespace' => 'App\Controllers\V1\Branches\Accounting\Receivables'], function ($routes) {
            $routes->post('',                    'Home::create/$1',      ['filter' => 'auth:scope=uri|3,module_code=acc_receivables,only_user']);
            $routes->get('',                     'Home::index/$1',       ['filter' => 'auth:scope=uri|3,module_code=acc_receivables']);
            $routes->get('(:num)',               'Home::show/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=acc_receivables']);
            $routes->put('(:num)',               'Home::update/$1/$2',   ['filter' => 'auth:scope=uri|3,module_code=acc_receivables,only_user']);
            $routes->delete('(:num)',            'Home::delete/$1/$2',   ['filter' => 'auth:scope=uri|3,module_code=acc_receivables,only_user']);
            $routes->patch('(:num)',             'Home::restore/$1/$2',  ['filter' => 'auth:scope=uri|3,module_code=acc_receivables,only_user']);
            $routes->put('',                     'Home::update/$1',      ['filter' => 'auth:scope=uri|3,module_code=acc_receivables,only_user']);
            $routes->delete('',                  'Home::delete/$1',      ['filter' => 'auth:scope=uri|3,module_code=acc_receivables,only_user']);
            $routes->patch('',                   'Home::restore/$1',     ['filter' => 'auth:scope=uri|3,module_code=acc_receivables,only_user']);
            $routes->post('overdue-sync',        'Home::overdueSync/$1', ['filter' => 'auth:scope=uri|3,module_code=acc_receivables,only_user']);
        });

        // Recaudación / Recibos de Caja
        $routes->group('receipts', ['namespace' => 'App\Controllers\V1\Branches\Accounting\Receipts'], function ($routes) {
            $routes->post('',              'Home::create/$1',     ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
            $routes->get('',               'Home::index/$1',      ['filter' => 'auth:scope=uri|3,module_code=acc_receipts']);
            $routes->put('(:num)/status',  'Home::status/$1/$2',  ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
            $routes->get('(:num)/detail',  'Home::detail/$1/$2',  ['filter' => 'auth:scope=uri|3,module_code=acc_receipts']);
            $routes->get('(:num)/pdf',     'Home::pdf/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=acc_receipts']);
            $routes->get('(:num)',         'Home::show/$1/$2',    ['filter' => 'auth:scope=uri|3,module_code=acc_receipts']);
            $routes->put('(:num)',         'Home::update/$1/$2',  ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);

            // Renglones / Obligaciones vinculadas al recibo (Draft)
            $routes->group('(:num)/items', function ($routes) {
                $routes->post('',         'Items::create/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->get('',          'Items::index/$1/$2',      ['filter' => 'auth:scope=uri|3,module_code=acc_receipts']);
                $routes->get('(:num)',    'Items::show/$1/$2/$3',    ['filter' => 'auth:scope=uri|3,module_code=acc_receipts']);
                $routes->put('(:num)',    'Items::update/$1/$2/$3',  ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->delete('(:num)', 'Items::delete/$1/$2/$3',  ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->patch('(:num)',  'Items::restore/$1/$2/$3', ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->put('',          'Items::update/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->delete('',       'Items::delete/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->patch('',        'Items::restore/$1/$2',    ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
            });

            // Medios de pago recibidos (Draft)
            $routes->group('(:num)/payments', function ($routes) {
                $routes->post('',         'Payments::create/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->get('',          'Payments::index/$1/$2',      ['filter' => 'auth:scope=uri|3,module_code=acc_receipts']);
                $routes->get('(:num)',    'Payments::show/$1/$2/$3',    ['filter' => 'auth:scope=uri|3,module_code=acc_receipts']);
                $routes->put('(:num)',    'Payments::update/$1/$2/$3',  ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->delete('(:num)', 'Payments::delete/$1/$2/$3',  ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->patch('(:num)',  'Payments::restore/$1/$2/$3', ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->put('',          'Payments::update/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->delete('',       'Payments::delete/$1/$2',     ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
                $routes->patch('',        'Payments::restore/$1/$2',    ['filter' => 'auth:scope=uri|3,module_code=acc_receipts,only_user']);
            });
        });

        // Saldos a Favor de Clientes (acc_client_credit)
        $routes->group('credits', ['namespace' => 'App\Controllers\V1\Branches\Accounting\Credits'], function ($routes) {
            $routes->get('',           'Home::index/$1',    ['filter' => 'auth:scope=uri|3,module_code=acc_client_credits']);
            $routes->get('(:num)',      'Home::show/$1/$2',  ['filter' => 'auth:scope=uri|3,module_code=acc_client_credits']);
            $routes->put('(:num)/void', 'Home::void/$1/$2',  ['filter' => 'auth:scope=uri|3,module_code=acc_client_credits,only_user']);
        });
    });
});

