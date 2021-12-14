<?php

declare(strict_types=1);

/**
 * @copyright  2020 Podlibre
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html AGPL3
 * @link       https://castopod.org/
 */

namespace App\Entities;

use App\Libraries\SimpleRSSElement;
use App\Models\ClipsModel;
use App\Models\EpisodeCommentModel;
use App\Models\MediaModel;
use App\Models\PersonModel;
use App\Models\PodcastModel;
use App\Models\PostModel;
use CodeIgniter\Entity\Entity;
use CodeIgniter\Files\File;
use CodeIgniter\I18n\Time;
use League\CommonMark\CommonMarkConverter;
use RuntimeException;

/**
 * @property int $id
 * @property int $podcast_id
 * @property Podcast $podcast
 * @property string $link
 * @property string $guid
 * @property string $slug
 * @property string $title
 * @property int $audio_id
 * @property Audio $audio
 * @property string $audio_file_analytics_url
 * @property string $audio_file_web_url
 * @property string $audio_file_opengraph_url
 * @property string|null $description Holds text only description, striped of any markdown or html special characters
 * @property string $description_markdown
 * @property string $description_html
 * @property int $cover_id
 * @property Image $cover
 * @property int|null $transcript_id
 * @property Media|null $transcript
 * @property string|null $transcript_remote_url
 * @property int|null $chapters_id
 * @property Media|null $chapters
 * @property string|null $chapters_remote_url
 * @property string|null $parental_advisory
 * @property int $number
 * @property int $season_number
 * @property string $type
 * @property bool $is_blocked
 * @property Location|null $location
 * @property string|null $location_name
 * @property string|null $location_geo
 * @property string|null $location_osm
 * @property array|null $custom_rss
 * @property string $custom_rss_string
 * @property int $posts_count
 * @property int $comments_count
 * @property int $created_by
 * @property int $updated_by
 * @property string $publication_status;
 * @property Time|null $published_at;
 * @property Time $created_at;
 * @property Time $updated_at;
 * @property Time|null $deleted_at;
 *
 * @property Person[] $persons;
 * @property Soundbite[] $soundbites;
 * @property string $embed_url;
 */
class Episode extends Entity
{
    protected Podcast $podcast;

    protected string $link;

    protected Audio $audio;

    protected string $audio_url;

    protected string $audio_analytics_url;

    protected string $audio_web_url;

    protected string $audio_opengraph_url;

    protected string $embed_url;

    protected Image $cover;

    protected ?string $description = null;

    protected ?Media $transcript;

    protected ?Media $chapters;

    /**
     * @var Person[]|null
     */
    protected ?array $persons = null;

    /**
     * @var Clip[]|null
     */
    protected ?array $clips = null;

    /**
     * @var Post[]|null
     */
    protected ?array $posts = null;

    /**
     * @var EpisodeComment[]|null
     */
    protected ?array $comments = null;

    protected ?Location $location = null;

    protected string $custom_rss_string;

    protected ?string $publication_status = null;

    /**
     * @var string[]
     */
    protected $dates = ['published_at', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'podcast_id' => 'integer',
        'guid' => 'string',
        'slug' => 'string',
        'title' => 'string',
        'audio_id' => 'integer',
        'description_markdown' => 'string',
        'description_html' => 'string',
        'cover_id' => '?integer',
        'transcript_id' => '?integer',
        'transcript_remote_url' => '?string',
        'chapters_id' => '?integer',
        'chapters_remote_url' => '?string',
        'parental_advisory' => '?string',
        'number' => '?integer',
        'season_number' => '?integer',
        'type' => 'string',
        'is_blocked' => 'boolean',
        'location_name' => '?string',
        'location_geo' => '?string',
        'location_osm' => '?string',
        'custom_rss' => '?json-array',
        'posts_count' => 'integer',
        'comments_count' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Saves an episode cover
     */
    public function setCover(?Image $cover = null): static
    {
        if ($cover === null) {
            return $this;
        }

        // Save image
        $cover->saveImage(
            config('Images')
                ->podcastCoverSizes,
            'podcasts/' . $this->getPodcast()->handle,
            $this->attributes['slug']
        );

        $this->attributes['cover_mimetype'] = $cover->mimetype;
        $this->attributes['cover_path'] = $cover->path;

        return $this;
    }

    public function getCover(): Image
    {
        if (! $this->cover instanceof Image) {
            $this->cover = (new MediaModel('image'))->getMediaById($this->cover_id);
        }

        return $this->cover;
    }

    public function getAudio(): Audio
    {
        if (! $this->audio) {
            $this->audio = (new MediaModel('audio'))->getMediaById($this->audio_id);
        }

        return $this->audio;
    }

    public function getTranscript(): ?Media
    {
        if ($this->transcript_id !== null && $this->transcript === null) {
            $this->transcript = (new MediaModel('document'))->getMediaById($this->transcript_id);
        }

        return $this->transcript;
    }

    public function getChaptersFile(): ?Media
    {
        if ($this->chapters_id !== null && $this->chapters === null) {
            $this->chapters = (new MediaModel('document'))->getMediaById($this->chapters_id);
        }

        return $this->chapters;
    }

    public function getAudioFileUrl(): string
    {
        helper('media');

        return media_base_url($this->audio->file_path);
    }

    public function getAudioFileAnalyticsUrl(): string
    {
        helper('analytics');

        // remove 'podcasts/' from audio file path
        $strippedAudioFilePath = substr($this->audio->file_path, 9);

        return generate_episode_analytics_url(
            $this->podcast_id,
            $this->id,
            $strippedAudioFilePath,
            $this->audio->duration,
            $this->audio->file_size,
            $this->audio->header_size,
            $this->published_at,
        );
    }

    public function getAudioFileWebUrl(): string
    {
        return $this->getAudioFileAnalyticsUrl() . '?_from=-+Website+-';
    }

    public function getAudioFileOpengraphUrl(): string
    {
        return $this->getAudioFileAnalyticsUrl() . '?_from=-+Open+Graph+-';
    }

    /**
     * Gets transcript url from transcript file uri if it exists or returns the transcript_remote_url which can be null.
     */
    public function getTranscriptUrl(): ?string
    {
        if ($this->transcript !== null) {
            return $this->transcript->url;
        }
        return $this->transcript_remote_url;
    }

    /**
     * Gets chapters file url from chapters file uri if it exists or returns the chapters_remote_url which can be null.
     */
    public function getChaptersFileUrl(): ?string
    {
        if ($this->chapters) {
            return $this->chapters->url;
        }

        return $this->chapters_remote_url;
    }

    /**
     * Returns the episode's persons
     *
     * @return Person[]
     */
    public function getPersons(): array
    {
        if ($this->id === null) {
            throw new RuntimeException('Episode must be created before getting persons.');
        }

        if ($this->persons === null) {
            $this->persons = (new PersonModel())->getEpisodePersons($this->podcast_id, $this->id);
        }

        return $this->persons;
    }

    /**
     * Returns the episode’s clips
     *
     * @return Clip[]
     */
    public function getClips(): array
    {
        if ($this->id === null) {
            throw new RuntimeException('Episode must be created before getting clips.');
        }

        if ($this->clips === null) {
            $this->clips = (new ClipsModel())->getEpisodeClips($this->getPodcast() ->id, $this->id);
        }

        return $this->clips;
    }

    /**
     * @return Post[]
     */
    public function getPosts(): array
    {
        if ($this->id === null) {
            throw new RuntimeException('Episode must be created before getting posts.');
        }

        if ($this->posts === null) {
            $this->posts = (new PostModel())->getEpisodePosts($this->id);
        }

        return $this->posts;
    }

    /**
     * @return EpisodeComment[]
     */
    public function getComments(): array
    {
        if ($this->id === null) {
            throw new RuntimeException('Episode must be created before getting comments.');
        }

        if ($this->comments === null) {
            $this->comments = (new EpisodeCommentModel())->getEpisodeComments($this->id);
        }

        return $this->comments;
    }

    public function getLink(): string
    {
        return url_to('episode', $this->getPodcast()->handle, $this->attributes['slug']);
    }

    public function getEmbedUrl(string $theme = null): string
    {
        return base_url(
            $theme
                ? route_to('embed-theme', $this->getPodcast() ->handle, $this->attributes['slug'], $theme,)
                : route_to('embed', $this->getPodcast()->handle, $this->attributes['slug']),
        );
    }

    public function setGuid(?string $guid = null): static
    {
        $this->attributes['guid'] = $guid === null ? $this->getLink() : $guid;

        return $this;
    }

    public function getPodcast(): ?Podcast
    {
        return (new PodcastModel())->getPodcastById($this->podcast_id);
    }

    public function setDescriptionMarkdown(string $descriptionMarkdown): static
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $this->attributes['description_markdown'] = $descriptionMarkdown;
        $this->attributes['description_html'] = $converter->convertToHtml($descriptionMarkdown);

        return $this;
    }

    public function getDescriptionHtml(?string $serviceSlug = null): string
    {
        $descriptionHtml = '';
        if (
            $this->getPodcast()
                ->partner_id !== null &&
            $this->getPodcast()
                ->partner_link_url !== null &&
            $this->getPodcast()
                ->partner_image_url !== null
        ) {
            $descriptionHtml .= "<div><a href=\"{$this->getPartnerLink(
                $serviceSlug,
            )}\" rel=\"sponsored noopener noreferrer\" target=\"_blank\"><img src=\"{$this->getPartnerImageUrl(
                $serviceSlug,
            )}\" alt=\"Partner image\" /></a></div>";
        }

        $descriptionHtml .= $this->attributes['description_html'];

        if ($this->getPodcast()->episode_description_footer_html) {
            $descriptionHtml .= "<footer>{$this->getPodcast()
                ->episode_description_footer_html}</footer>";
        }

        return $descriptionHtml;
    }

    public function getDescription(): string
    {
        if ($this->description === null) {
            $this->description = trim(
                preg_replace('~\s+~', ' ', strip_tags($this->attributes['description_html'])),
            );
        }

        return $this->description;
    }

    public function getPublicationStatus(): string
    {
        if ($this->publication_status === null) {
            if ($this->published_at === null) {
                $this->publication_status = 'not_published';
            } elseif ($this->published_at->isBefore(Time::now())) {
                $this->publication_status = 'published';
            } else {
                $this->publication_status = 'scheduled';
            }
        }

        return $this->publication_status;
    }

    /**
     * Saves the location name and fetches OpenStreetMap info
     */
    public function setLocation(?Location $location = null): static
    {
        if ($location === null) {
            $this->attributes['location_name'] = null;
            $this->attributes['location_geo'] = null;
            $this->attributes['location_osm'] = null;

            return $this;
        }

        if (
            ! isset($this->attributes['location_name']) ||
            $this->attributes['location_name'] !== $location->name
        ) {
            $location->fetchOsmLocation();

            $this->attributes['location_name'] = $location->name;
            $this->attributes['location_geo'] = $location->geo;
            $this->attributes['location_osm'] = $location->osm;
        }

        return $this;
    }

    public function getLocation(): ?Location
    {
        if ($this->location_name === null) {
            return null;
        }

        if ($this->location === null) {
            $this->location = new Location($this->location_name, $this->location_geo, $this->location_osm);
        }

        return $this->location;
    }

    /**
     * Get custom rss tag as XML String
     */
    public function getCustomRssString(): string
    {
        if ($this->custom_rss === null) {
            return '';
        }

        helper('rss');

        $xmlNode = (new SimpleRSSElement(
            '<?xml version="1.0" encoding="utf-8"?><rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:podcast="https://github.com/Podcastindex-org/podcast-namespace/blob/main/docs/1.0.md" xmlns:content="http://purl.org/rss/1.0/modules/content/" version="2.0"/>',
        ))
            ->addChild('channel')
            ->addChild('item');
        array_to_rss([
            'elements' => $this->custom_rss,
        ], $xmlNode);

        return str_replace(['<item>', '</item>'], '', $xmlNode->asXML());
    }

    /**
     * Saves custom rss tag into json
     */
    public function setCustomRssString(?string $customRssString = null): static
    {
        if ($customRssString === '') {
            $this->attributes['custom_rss'] = null;
            return $this;
        }

        helper('rss');
        $customRssArray = rss_to_array(
            simplexml_load_string(
                '<?xml version="1.0" encoding="utf-8"?><rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:podcast="https://github.com/Podcastindex-org/podcast-namespace/blob/main/docs/1.0.md" xmlns:content="http://purl.org/rss/1.0/modules/content/" version="2.0"><channel><item>' .
                    $customRssString .
                    '</item></channel></rss>',
            ),
        )['elements'][0]['elements'][0];

        if (array_key_exists('elements', $customRssArray)) {
            $this->attributes['custom_rss'] = json_encode($customRssArray['elements']);
        } else {
            $this->attributes['custom_rss'] = null;
        }

        return $this;
    }

    public function getPartnerLink(?string $serviceSlug = null): string
    {
        $partnerLink =
            rtrim($this->getPodcast()->partner_link_url, '/') .
            '?pid=' .
            $this->getPodcast()
                ->partner_id .
            '&guid=' .
            urlencode($this->attributes['guid']);

        if ($serviceSlug !== null) {
            $partnerLink .= '&_from=' . $serviceSlug;
        }

        return $partnerLink;
    }

    public function getPartnerImageUrl(string $serviceSlug = null): string
    {
        return rtrim($this->getPodcast()->partner_image_url, '/') .
            '?pid=' .
            $this->getPodcast()
                ->partner_id .
            '&guid=' .
            urlencode($this->attributes['guid']) .
            ($serviceSlug !== null ? '&_from=' . $serviceSlug : '');
    }
}
