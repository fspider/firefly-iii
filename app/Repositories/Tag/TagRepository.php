<?php
/**
 * TagRepository.php
 * Copyright (c) 2019 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace FireflyIII\Repositories\Tag;

use Carbon\Carbon;
use DB;
use FireflyIII\Factory\TagFactory;
use FireflyIII\Helpers\Collector\GroupCollectorInterface;
use FireflyIII\Models\Location;
use FireflyIII\Models\Tag;
use FireflyIII\Models\TransactionType;
use FireflyIII\User;
use Illuminate\Support\Collection;
use Log;

/**
 * Class TagRepository.
 *
 */
class TagRepository implements TagRepositoryInterface
{
    /** @var User */
    private $user;

    /**
     * Constructor.
     */
    public function __construct()
    {
        if ('testing' === config('app.env')) {
            Log::warning(sprintf('%s should not be instantiated in the TEST environment!', get_class($this)));
        }
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->user->tags()->count();
    }

    /**
     * @param Tag $tag
     *
     * @return bool
     * @throws \Exception
     */
    public function destroy(Tag $tag): bool
    {
        $tag->transactionJournals()->sync([]);
        $tag->delete();

        return true;
    }

    /**
     * Destroy all tags.
     */
    public function destroyAll(): void
    {
        $tags = $this->get();
        /** @var Tag $tag */
        foreach ($tags as $tag) {
            DB::table('tag_transaction_journal')->where('tag_id', $tag->id)->delete();
            $tag->delete();
        }
    }

    /**
     * @param Tag    $tag
     * @param Carbon $start
     * @param Carbon $end
     *
     * @return string
     */
    public function earnedInPeriod(Tag $tag, Carbon $start, Carbon $end): string
    {
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);

        $collector->setUser($this->user);
        $collector->setRange($start, $end)->setTypes([TransactionType::DEPOSIT])->setTag($tag);

        return $collector->getSum();
    }

    /**
     * @param Tag    $tag
     * @param Carbon $start
     * @param Carbon $end
     *
     * @return array
     */
    public function expenseInPeriod(Tag $tag, Carbon $start, Carbon $end): array
    {
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);

        $collector->setUser($this->user);
        $collector->setRange($start, $end)->setTypes([TransactionType::WITHDRAWAL])->setTag($tag);

        return $collector->getExtractedJournals();
    }

    /**
     * @param string $tag
     *
     * @return Tag|null
     */
    public function findByTag(string $tag): ?Tag
    {
        return $this->user->tags()->where('tag', $tag)->first();
    }

    /**
     * @param int $tagId
     *
     * @return Tag|null
     */
    public function findNull(int $tagId): ?Tag
    {
        return $this->user->tags()->find($tagId);
    }

    /**
     * @param Tag $tag
     *
     * @return Carbon|null
     */
    public function firstUseDate(Tag $tag): ?Carbon
    {
        $journal = $tag->transactionJournals()->orderBy('date', 'ASC')->first();
        if (null !== $journal) {
            return $journal->date;
        }

        return null;
    }

    /**
     * @return Collection
     */
    public function get(): Collection
    {
        /** @var Collection $tags */
        $tags = $this->user->tags()->orderBy('tag', 'ASC')->get();

        return $tags;
    }

    /**
     * @inheritDoc
     */
    public function getLocation(Tag $tag): ?Location
    {
        return $tag->locations()->first();
    }

    /**
     * @param int|null $year
     *
     * @return Collection
     */
    public function getTagsInYear(?int $year): array
    {
        // get all tags in the year (if present):
        $tagQuery = $this->user->tags()->with(['locations'])->orderBy('tags.tag');

        // add date range (or not):
        if (null === $year) {
            Log::debug('Get tags without a date.');
            $tagQuery->whereNull('tags.date');
        }

        if (null !== $year) {
            Log::debug(sprintf('Get tags with year %s.', $year));
            $tagQuery->where('tags.date', '>=', $year . '-01-01 00:00:00')->where('tags.date', '<=', $year . '-12-31 23:59:59');
        }
        $collection = $tagQuery->get();
        $return     = [];
        /** @var Tag $tag */
        foreach ($collection as $tag) {
            // return value for tag cloud:
            $return[$tag->id] = [
                'tag'        => $tag->tag,
                'id'         => $tag->id,
                'created_at' => $tag->created_at,
                'location'   => $tag->locations->first(),
            ];
        }

        return $return;
    }

    /**
     * @param Tag    $tag
     * @param Carbon $start
     * @param Carbon $end
     *
     * @return array
     */
    public function incomeInPeriod(Tag $tag, Carbon $start, Carbon $end): array
    {
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);

        $collector->setUser($this->user);
        $collector->setRange($start, $end)->setTypes([TransactionType::DEPOSIT])->setTag($tag);

        return $collector->getExtractedJournals();
    }

    /**
     * @param Tag $tag
     *
     * @return Carbon|null
     */
    public function lastUseDate(Tag $tag): ?Carbon
    {
        $journal = $tag->transactionJournals()->orderBy('date', 'DESC')->first();
        if (null !== $journal) {
            return $journal->date;
        }

        return null;
    }

    /**
     * Will return the newest tag (if known) or NULL.
     *
     * @return Tag|null
     */
    public function newestTag(): ?Tag
    {
        return $this->user->tags()->whereNotNull('date')->orderBy('date', 'DESC')->first();
    }

    /**
     * @return Tag
     */
    public function oldestTag(): ?Tag
    {
        return $this->user->tags()->whereNotNull('date')->orderBy('date', 'ASC')->first();
    }

    /**
     * Find one or more tags based on the query.
     *
     * @param string $query
     *
     * @return Collection
     */
    public function searchTag(string $query): Collection
    {
        $search = sprintf('%%%s%%', $query);

        return $this->user->tags()->where('tag', 'LIKE', $search)->get(['tags.*']);
    }

    /**
     * Search the users tags.
     *
     * @param string $query
     *
     * @return Collection
     */
    public function searchTags(string $query): Collection
    {
        /** @var Collection $tags */
        $tags = $this->user->tags()->orderBy('tag', 'ASC');
        if ('' !== $query) {
            $search = sprintf('%%%s%%', $query);
            $tags->where('tag', 'LIKE', $search);
        }

        return $tags->get();
    }

    /**
     * @param User $user
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @param Tag    $tag
     * @param Carbon $start
     * @param Carbon $end
     *
     * @return string
     */
    public function spentInPeriod(Tag $tag, Carbon $start, Carbon $end): string
    {
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);

        $collector->setUser($this->user);
        $collector->setRange($start, $end)->setTypes([TransactionType::WITHDRAWAL])->setTag($tag);

        return $collector->getSum();
    }

    /**
     * @param array $data
     *
     * @return Tag
     */
    public function store(array $data): Tag
    {
        /** @var TagFactory $factory */
        $factory = app(TagFactory::class);
        $factory->setUser($this->user);

        return $factory->create($data);
    }

    /**
     * @param Tag         $tag
     * @param Carbon|null $start
     * @param Carbon|null $end
     *
     * @return array
     *
     */
    public function sumsOfTag(Tag $tag, ?Carbon $start, ?Carbon $end): array
    {
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);

        if (null !== $start && null !== $end) {
            $collector->setRange($start, $end);
        }

        $collector->setTag($tag)->withAccountInformation();
        $journals = $collector->getExtractedJournals();

        $sums = [
            TransactionType::WITHDRAWAL      => '0',
            TransactionType::DEPOSIT         => '0',
            TransactionType::TRANSFER        => '0',
            TransactionType::RECONCILIATION  => '0',
            TransactionType::OPENING_BALANCE => '0',
        ];

        /** @var array $journal */
        foreach ($journals as $journal) {
            $amount = app('steam')->positive((string)$journal['amount']);
            $type   = $journal['transaction_type_type'];
            if (TransactionType::WITHDRAWAL === $type) {
                $amount = bcmul($amount, '-1');
            }
            $sums[$type] = bcadd($sums[$type], $amount);
        }

        return $sums;
    }

    /**
     * Generates a tag cloud.
     *
     * @param int|null $year
     *
     * @return array
     * @deprecated
     */
    public function tagCloud(?int $year): array
    {
        // Some vars
        $tags = $this->getTagsInYear($year);

        $max           = $this->getMaxAmount($tags);
        $min           = $this->getMinAmount($tags);
        $diff          = bcsub($max, $min);
        $return        = [];
        $minimumFont   = '12'; // default scale is from 12 to 24, so 12 points.
        $maxPoints     = '12';
        $pointsPerCoin = '0';

        Log::debug(sprintf('Minimum is %s, maximum is %s, difference is %s', $min, $max, $diff));

        if (0 !== bccomp($diff, '0')) { // for each full coin in tag, add so many points
            // minus the smallest tag.
            $pointsPerCoin = bcdiv($maxPoints, $diff);
        }

        Log::debug(sprintf('Each coin in a tag earns it %s points', $pointsPerCoin));
        /** @var Tag $tag */
        foreach ($tags as $tag) {
            $amount       = (string)$tag->amount_sum;
            $amount       = '' === $amount ? '0' : $amount;
            $amountMin    = bcsub($amount, $min);
            $pointsForTag = bcmul($amountMin, $pointsPerCoin);
            $fontSize     = bcadd($minimumFont, $pointsForTag);
            Log::debug(sprintf('Tag "%s": Amount is %s, so points is %s', $tag->tag, $amount, $fontSize));

            // return value for tag cloud:
            $return[$tag->id] = [
                'size'       => $fontSize,
                'tag'        => $tag->tag,
                'id'         => $tag->id,
                'created_at' => $tag->created_at,
                'location'   => $this->getLocation($tag),
            ];
        }

        return $return;
    }

    /**
     * @param Tag    $tag
     * @param Carbon $start
     * @param Carbon $end
     *
     * @return array
     */
    public function transferredInPeriod(Tag $tag, Carbon $start, Carbon $end): array
    {
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector->setUser($this->user);
        $collector->setRange($start, $end)->setTypes([TransactionType::TRANSFER])->setTag($tag);

        return $collector->getExtractedJournals();
    }

    /**
     * @param Tag   $tag
     * @param array $data
     *
     * @return Tag
     */
    public function update(Tag $tag, array $data): Tag
    {
        $tag->tag         = $data['tag'];
        $tag->date        = $data['date'];
        $tag->description = $data['description'];
        $tag->latitude    = null;
        $tag->longitude   = null;
        $tag->zoomLevel   = null;
        $tag->save();

        // update, delete or create location:
        $updateLocation = $data['update_location'] ?? false;

        // location must be updated?
        if (true === $updateLocation) {
            // if all set to NULL, delete
            if (null === $data['latitude'] && null === $data['longitude'] && null === $data['zoom_level']) {
                $tag->locations()->delete();
            }

            // otherwise, update or create.
            if (!(null === $data['latitude'] && null === $data['longitude'] && null === $data['zoom_level'])) {
                $location = $this->getLocation($tag);
                if (null === $location) {
                    $location = new Location;
                    $location->locatable()->associate($tag);
                }

                $location->latitude   = $data['latitude'] ?? config('firefly.default_location.latitude');
                $location->longitude  = $data['longitude'] ?? config('firefly.default_location.longitude');
                $location->zoom_level = $data['zoom_level'] ?? config('firefly.default_location.zoom_level');
                $location->save();
            }
        }

        return $tag;
    }

    /**
     * @param Collection $tags
     *
     * @return string
     */
    private function getMaxAmount(Collection $tags): string
    {
        $max = '0';
        /** @var Tag $tag */
        foreach ($tags as $tag) {
            $amount = (string)$tag->amount_sum;
            $amount = '' === $amount ? '0' : $amount;
            $max    = 1 === bccomp($amount, $max) ? $amount : $max;

        }
        Log::debug(sprintf('Maximum is %s.', $max));

        return $max;
    }

    /**
     * @param Collection $tags
     *
     * @return string
     *
     */
    private function getMinAmount(Collection $tags): string
    {
        $min = null;

        /** @var Tag $tag */
        foreach ($tags as $tag) {
            $amount = (string)$tag->amount_sum;
            $amount = '' === $amount ? '0' : $amount;

            if (null === $min) {
                $min = $amount;
            }
            $min = -1 === bccomp($amount, $min) ? $amount : $min;
        }


        if (null === $min) {
            $min = '0';
        }
        Log::debug(sprintf('Minimum is %s.', $min));

        return $min;
    }
}
