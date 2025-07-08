<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Mail\Queue;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Support\Carbon;
use LengthOfRope\TreeHouse\Support\Collection;

/**
 * Queued Mail ActiveRecord Model
 * 
 * Represents a queued email in the database with performance tracking,
 * retry logic, and comprehensive status management.
 * 
 * @package LengthOfRope\TreeHouse\Mail\Queue
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class QueuedMail extends ActiveRecord
{
    /**
     * The table associated with the model
     */
    protected string $table = 'queued_mails';

    /**
     * The attributes that are mass assignable
     */
    protected array $fillable = [
        'to_addresses',
        'from_address',
        'cc_addresses',
        'bcc_addresses',
        'subject',
        'body_text',
        'body_html',
        'attachments',
        'headers',
        'mailer',
        'priority',
        'max_attempts',
        'available_at',
    ];

    /**
     * The attributes that should be cast
     */
    protected array $casts = [
        'to_addresses' => 'json',
        'from_address' => 'json',
        'cc_addresses' => 'json',
        'bcc_addresses' => 'json',
        'attachments' => 'json',
        'headers' => 'json',
        'priority' => 'int',
        'max_attempts' => 'int',
        'attempts' => 'int',
        'queue_time' => 'float',
        'processing_time' => 'float',
        'delivery_time' => 'float',
        'available_at' => 'datetime',
        'reserved_at' => 'datetime',
        'reserved_until' => 'datetime',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'failed_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Override castAttribute to handle Carbon objects properly
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $cast = $this->casts[$key] ?? null;
        
        if (!$cast) {
            return $value;
        }

        return match ($cast) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => is_array($value) ? $value : json_decode($value, true),
            'object' => is_object($value) ? $value : json_decode($value),
            'datetime', 'date', 'timestamp' => $value instanceof Carbon ? $value : Carbon::parse($value),
            default => $value
        };
    }

    /**
     * Queue status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    /**
     * Priority constants
     */
    public const PRIORITY_HIGHEST = 1;
    public const PRIORITY_HIGH = 2;
    public const PRIORITY_NORMAL = 5;
    public const PRIORITY_LOW = 8;
    public const PRIORITY_LOWEST = 10;

    /**
     * Mark the email as queued and calculate queue time
     */
    public function markAsQueued(): void
    {
        $this->available_at = Carbon::now();
        $this->save();
    }

    /**
     * Start processing the email and track processing time
     */
    public function startProcessing(): void
    {
        $now = Carbon::now();
        
        // Calculate queue time (time from creation to processing start)
        if ($this->created_at) {
            $this->queue_time = $now->diffInSeconds($this->created_at, true);
        }
        
        // Mark as reserved for processing
        $this->reserved_at = $now;
        $this->reserved_until = $now->addMinutes(5); // 5-minute processing timeout
        
        $this->save();
    }

    /**
     * Mark the email as successfully processed and sent
     * 
     * @param float $deliveryTime Time taken for actual mail delivery in seconds
     */
    public function markAsProcessed(float $deliveryTime): void
    {
        $now = Carbon::now();
        
        // Calculate processing time (time from reservation to completion)
        if ($this->reserved_at) {
            $this->processing_time = $now->diffInSeconds($this->reserved_at, true);
        }
        
        // Set delivery time
        $this->delivery_time = $deliveryTime;
        
        // Mark as sent
        $this->sent_at = $now;
        $this->failed_at = null;
        $this->error_message = null;
        
        // Clear reservation
        $this->reserved_at = null;
        $this->reserved_until = null;
        
        $this->save();
    }

    /**
     * Mark the email as failed and calculate next retry time
     * 
     * @param string $error Error message
     * @param int $retryDelaySeconds Base retry delay in seconds
     */
    public function markAsFailed(string $error, int $retryDelaySeconds = 300): void
    {
        $now = Carbon::now();
        
        // Increment attempts
        $this->attempts++;
        $this->last_attempt_at = $now;
        $this->error_message = $error;
        
        // Calculate processing time if we were processing
        if ($this->reserved_at) {
            $this->processing_time = $now->diffInSeconds($this->reserved_at, true);
        }
        
        // Clear reservation
        $this->reserved_at = null;
        $this->reserved_until = null;
        
        // Check if we should retry
        if ($this->canRetry()) {
            // Calculate next retry time with exponential backoff
            $retryDelay = $this->calculateRetryDelay($retryDelaySeconds);
            $this->next_retry_at = $now->addSeconds($retryDelay);
            $this->available_at = $this->next_retry_at;
        } else {
            // Mark as permanently failed
            $this->failed_at = $now;
            $this->next_retry_at = null;
        }
        
        $this->save();
    }

    /**
     * Check if the email can be retried
     */
    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    /**
     * Calculate retry delay with exponential backoff
     * 
     * @param int $baseDelay Base retry delay in seconds
     * @return int Calculated delay in seconds
     */
    public function calculateRetryDelay(int $baseDelay = 300): int
    {
        // Exponential backoff: delay = baseDelay * (2 ^ (attempts - 1))
        $delay = $baseDelay * (2 ** ($this->attempts - 1));
        
        // Cap at maximum delay (1 hour)
        return min($delay, 3600);
    }

    /**
     * Get the current status of the queued mail
     */
    public function getStatus(): string
    {
        if ($this->sent_at) {
            return self::STATUS_SENT;
        }
        
        if ($this->failed_at) {
            return self::STATUS_FAILED;
        }
        
        if ($this->reserved_at && $this->reserved_until && Carbon::now() < $this->reserved_until) {
            return self::STATUS_PROCESSING;
        }
        
        return self::STATUS_PENDING;
    }

    /**
     * Check if the email is available for processing
     */
    public function isAvailable(): bool
    {
        $now = Carbon::now();
        
        // Must be available and not already sent or permanently failed
        return $now >= $this->available_at
            && !$this->sent_at
            && !$this->failed_at
            && (!$this->reserved_at || $now > $this->reserved_until);
    }

    /**
     * Check if the email has expired (reservation timeout)
     */
    public function hasExpired(): bool
    {
        return $this->reserved_until && Carbon::now() > $this->reserved_until;
    }

    /**
     * Release the reservation on the email
     */
    public function releaseReservation(): void
    {
        $this->reserved_at = null;
        $this->reserved_until = null;
        $this->save();
    }

    /**
     * Get emails available for processing
     * 
     * @param string|null $mailer Filter by mailer
     * @param int $limit Maximum number of emails to return
     * @return Collection
     */
    public static function getAvailableForProcessing(?string $mailer = null, int $limit = 10): Collection
    {
        $now = Carbon::now();
        $query = static::query()
            ->where('available_at', '<=', $now)
            ->whereNull('sent_at')
            ->whereNull('failed_at')
            ->where('reserved_at', null)
            ->orWhere('reserved_until', '<', $now)
            ->orderBy('priority', 'asc')
            ->orderBy('available_at', 'asc')
            ->limit($limit);
            
        if ($mailer) {
            $query->where('mailer', $mailer);
        }
        
        return $query->get();
    }

    /**
     * Get failed emails that can be retried
     * 
     * @param int $limit Maximum number of emails to return
     * @return Collection
     */
    public static function getFailedForRetry(int $limit = 10): Collection
    {
        $now = Carbon::now();
        return static::query()
            ->whereNull('sent_at')
            ->whereNull('failed_at')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', $now)
            ->orderBy('priority', 'asc')
            ->orderBy('next_retry_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get queue statistics
     * 
     * @return array
     */
    public static function getQueueStats(): array
    {
        $total = static::query()->count();
        $pending = static::query()
            ->whereNull('sent_at')
            ->whereNull('failed_at')
            ->count();
        $processing = static::query()
            ->whereNotNull('reserved_at')
            ->where('reserved_until', '>', Carbon::now())
            ->count();
        $sent = static::query()->whereNotNull('sent_at')->count();
        $failed = static::query()->whereNotNull('failed_at')->count();
        
        return [
            'total' => $total,
            'pending' => $pending,
            'processing' => $processing,
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    /**
     * Clean up old processed emails
     * 
     * @param int $daysOld Number of days old to consider for cleanup
     * @return int Number of emails cleaned up
     */
    public static function cleanupOldEmails(int $daysOld = 7): int
    {
        $cutoff = Carbon::now()->subDays($daysOld);
        
        $deletedSent = static::query()
            ->where('sent_at', '<', $cutoff)
            ->delete();
            
        $deletedFailed = static::query()
            ->where('failed_at', '<', $cutoff)
            ->delete();
            
        return $deletedSent + $deletedFailed;
    }

    /**
     * Get performance metrics for the queue
     * 
     * @param int $hours Number of hours to look back
     * @return array
     */
    public static function getPerformanceMetrics(int $hours = 24): array
    {
        $since = Carbon::now()->subHours($hours);
        
        // Get basic metrics using separate queries since TreeHouse QueryBuilder doesn't have selectRaw
        $processedEmails = static::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('queue_time')
            ->get();

        $totalProcessed = $processedEmails->count();
        
        if ($totalProcessed === 0) {
            return [
                'avg_queue_time' => 0,
                'avg_processing_time' => 0,
                'avg_delivery_time' => 0,
                'max_queue_time' => 0,
                'max_processing_time' => 0,
                'max_delivery_time' => 0,
                'total_processed' => 0,
            ];
        }

        // Calculate metrics from the collection
        $queueTimes = $processedEmails->pluck('queue_time')->filter();
        $processingTimes = $processedEmails->pluck('processing_time')->filter();
        $deliveryTimes = $processedEmails->pluck('delivery_time')->filter();
            
        return [
            'avg_queue_time' => round($queueTimes->avg() ?? 0, 3),
            'avg_processing_time' => round($processingTimes->avg() ?? 0, 3),
            'avg_delivery_time' => round($deliveryTimes->avg() ?? 0, 3),
            'max_queue_time' => round($queueTimes->max() ?? 0, 3),
            'max_processing_time' => round($processingTimes->max() ?? 0, 3),
            'max_delivery_time' => round($deliveryTimes->max() ?? 0, 3),
            'total_processed' => $totalProcessed,
        ];
    }
}