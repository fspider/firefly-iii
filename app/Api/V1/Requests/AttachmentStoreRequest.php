<?php
/**
 * AttachmentStoreRequest.php
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

namespace FireflyIII\Api\V1\Requests;

use FireflyIII\Models\Bill;
use FireflyIII\Models\ImportJob;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Rules\IsValidAttachmentModel;

/**
 * Class AttachmentStoreRequest
 *
 * @codeCoverageIgnore
 */
class AttachmentStoreRequest extends Request
{
    /**
     * Authorize logged in users.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Only allow authenticated users
        return auth()->check();
    }

    /**
     * Get all data from the request.
     *
     * @return array
     */
    public function getAll(): array
    {
        return [
            'filename' => $this->string('filename'),
            'title'    => $this->string('title'),
            'notes'    => $this->nlString('notes'),
            'model'    => $this->string('attachable_type'),
            'model_id' => $this->integer('attachable_id'),
        ];
    }

    /**
     * The rules that the incoming request must be matched against.
     *
     * @return array
     */
    public function rules(): array
    {
        $models = implode(
            ',',
            [
                str_replace('FireflyIII\\Models\\', '', Bill::class),
                str_replace('FireflyIII\\Models\\', '', ImportJob::class),
                str_replace('FireflyIII\\Models\\', '', TransactionJournal::class),
            ]
        );
        $model  = $this->string('attachable_type');

        return [
            'filename'        => 'required|between:1,255',
            'title'           => 'between:1,255',
            'notes'           => 'between:1,65000',
            'attachable_type' => sprintf('required|in:%s', $models),
            'attachable_id'   => ['required', 'numeric', new IsValidAttachmentModel($model)],
        ];
    }
}
