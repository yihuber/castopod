<?php

declare(strict_types=1);

/**
 * @copyright  2020 Ad Aures
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html AGPL3
 * @link       https://castopod.org/
 */

namespace App\Entities\Clip;

use App\Entities\Episode;
use App\Entities\Media\Audio;
use App\Entities\Media\Video;
use App\Entities\Podcast;
use App\Models\EpisodeModel;
use App\Models\MediaModel;
use App\Models\PodcastModel;
use App\Models\UserModel;
use CodeIgniter\Entity\Entity;
use CodeIgniter\Files\File;
use CodeIgniter\I18n\Time;
use Modules\Auth\Entities\User;

/**
 * @property int $id
 * @property int $podcast_id
 * @property Podcast $podcast
 * @property int $episode_id
 * @property Episode $episode
 * @property string $title
 * @property double $start_time
 * @property double $end_time
 * @property double $duration
 * @property string $type
 * @property int|null $media_id
 * @property Video|Audio|null $media
 * @property array|null $metadata
 * @property string $status
 * @property string $logs
 * @property User $user
 * @property int $created_by
 * @property int $updated_by
 * @property Time|null $job_started_at
 * @property Time|null $job_ended_at
 */
class BaseClip extends Entity
{
    /**
     * @var Video|Audio|null
     */
    protected $media;

    protected ?int $job_duration = null;

    protected ?float $end_time = null;

    /**
     * @var string[]
     */
    protected $dates = ['created_at', 'updated_at', 'job_started_at', 'job_ended_at'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'podcast_id' => 'integer',
        'episode_id' => 'integer',
        'title' => 'string',
        'start_time' => 'double',
        'duration' => 'double',
        'type' => 'string',
        'media_id' => '?integer',
        'metadata' => '?json-array',
        'status' => 'string',
        'logs' => 'string',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(array $data = null)
    {
        parent::__construct($data);
    }

    public function getJobDuration(): ?int
    {
        if ($this->job_duration === null && $this->job_started_at && $this->job_ended_at) {
            $this->job_duration = ($this->job_started_at->difference($this->job_ended_at))
                ->getSeconds();
        }

        return $this->job_duration;
    }

    public function getEndTime(): float
    {
        if ($this->end_time === null) {
            $this->end_time = $this->start_time + $this->duration;
        }

        return $this->end_time;
    }

    public function getPodcast(): ?Podcast
    {
        return (new PodcastModel())->getPodcastById($this->podcast_id);
    }

    public function getEpisode(): ?Episode
    {
        return (new EpisodeModel())->getEpisodeById($this->episode_id);
    }

    public function getUser(): ?User
    {
        return (new UserModel())->find($this->created_by);
    }

    public function setMedia(string $filePath = null): static
    {
        if ($filePath === null) {
            return $this;
        }

        $file = new File($filePath);

        if ($this->media_id !== null) {
            $this->getMedia()
                ->setFile($file);
            $this->getMedia()
                ->updated_by = (int) user_id();
            (new MediaModel('audio'))->updateMedia($this->getMedia());
        } else {
            $media = new Audio([
                'file_path' => $filePath,
                'language_code' => $this->getPodcast()
                    ->language_code,
                'uploaded_by' => $this->attributes['created_by'],
                'updated_by' => $this->attributes['created_by'],
            ]);
            $media->setFile($file);

            $this->attributes['media_id'] = (new MediaModel())->saveMedia($media);
        }

        return $this;
    }

    public function getMedia(): Audio | Video | null
    {
        if ($this->media_id !== null && $this->media === null) {
            $this->media = (new MediaModel($this->type))->getMediaById($this->media_id);
        }

        return $this->media;
    }
}
