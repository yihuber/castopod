<?php

/**
 * @copyright  2020 Podlibre
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html AGPL3
 * @link       https://castopod.org/
 */

return [
    'warning' =>
        'This procedure may take a long time.<br/>As the current version does not show any progress while it runs, you will not see anything updated until it is done.<br/>In case of timeout error, increase `max_execution_time` value.',
    'old_podcast_section_title' => 'The podcast to import',
    'old_podcast_section_subtitle' =>
        'Make sure you own the rights for this podcast before importing it. Copying and broadcasting a podcast without the proper rights is piracy and is liable to prosecution.',
    'imported_feed_url' => 'Feed URL',
    'imported_feed_url_hint' => 'The feed must be in xml or rss format.',
    'new_podcast_section_title' => 'The new podcast',
    'name' => 'Name',
    'name_hint' => 'Used for generating the podcast URL.',
    'advanced_params_section_title' => 'Advanced parameters',
    'advanced_params_section_subtitle' =>
        'Keep the default values if you have no idea of what the fields are for.',
    'slug_field' => [
        'label' => 'Which field should be used to calculate episode slug',
        'link' => '&lt;link&gt;',
        'title' => '&lt;title&gt;',
    ],
    'description_field' => [
        'label' => 'Source field used for episode description / show notes',
        'description' => '&lt;description&gt;',
        'summary' => '&lt;itunes:summary&gt;',
        'subtitle_summary' =>
            '&lt;itunes:subtitle&gt; + &lt;itunes:summary&gt;',
    ],
    'force_renumber' => 'Force episodes renumbering',
    'force_renumber_hint' =>
        'Use this if your podcast does not have episode numbers but wish to set them during import.',
    'season_number' => 'Season number',
    'season_number_hint' =>
        'Use this if your podcast does not have a season number but wish to set one during import. Leave blank otherwise.',
    'max_episodes' => 'Maximum number of episodes to import',
    'max_episodes_hint' => 'Leave blank to import all episodes',
    'lock_import' =>
        'This feed is protected. You cannot import it. If you are the owner, unprotect it on the origin platform.',
    'submit' => 'Import podcast',
];
