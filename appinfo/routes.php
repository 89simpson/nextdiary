<?php
/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\NextDiary\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
        // Page routes
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#index', 'url' => '/day/{date}', 'verb' => 'GET', 'postfix' => 'day'],
        ['name' => 'page#index', 'url' => '/entry/{id}', 'verb' => 'GET', 'postfix' => 'entryPage'],
        ['name' => 'page#index', 'url' => '/date/{date}', 'verb' => 'GET', 'postfix' => 'catchAll'],

        // New API (v0.0.2): multiple entries per day
        ['name' => 'page#get_entries_by_date', 'url' => '/api/entries/{date}', 'verb' => 'GET'],
        ['name' => 'page#get_entry_by_id', 'url' => '/api/entry/{id}', 'verb' => 'GET'],
        ['name' => 'page#create_entry', 'url' => '/api/entry/{date}', 'verb' => 'POST'],
        ['name' => 'page#update_entry_by_id', 'url' => '/api/entry/{id}', 'verb' => 'PUT'],
        ['name' => 'page#delete_entry', 'url' => '/api/entry/{id}', 'verb' => 'DELETE'],
        ['name' => 'page#get_last_entries', 'url' => '/api/last-entries/{amount}', 'verb' => 'GET'],
        ['name' => 'page#get_entry_dates', 'url' => '/api/entry-dates', 'verb' => 'GET'],

        // Tag API (v0.0.3)
        ['name' => 'page#get_tags', 'url' => '/api/tags', 'verb' => 'GET'],
        ['name' => 'page#get_entries_by_tag', 'url' => '/api/entries/tag/{tagId}', 'verb' => 'GET'],
        ['name' => 'page#index', 'url' => '/tag/{tagId}', 'verb' => 'GET', 'postfix' => 'tagPage'],

        // Legacy API (backward compatible)
        ['name' => 'page#get_entry', 'url' => '/entry/{date}', 'verb' => 'GET', 'postfix' => 'legacy'],
        ['name' => 'page#get_last_entries', 'url' => '/entries/{amount}', 'verb' => 'GET', 'postfix' => 'legacy'],
        ['name' => 'page#get_entry_dates', 'url' => '/entry-dates', 'verb' => 'GET', 'postfix' => 'legacy'],
        ['name' => 'page#update_entry', 'url' => '/entry/{date}', 'verb' => 'PUT'],

        // Export
        ['name' => 'export#get_markdown', 'url' => '/export/markdown', 'verb' => 'GET'],
        ['name' => 'export#get_pdf', 'url' => '/export/pdf', 'verb' => 'GET'],
    ]
];
