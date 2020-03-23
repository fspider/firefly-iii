<?php

declare(strict_types=1);
namespace FireflyIII\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionStatu extends Model
{
    use SoftDeletes;
    /**
     *
     */
    public const AWAITING_APPROVAL = 'Awaiting Approval';
    public const AWAITING_PAYMENT = 'Awaiting Payment';
    public const DECLINED = 'Declined';
    public const SUCCESS = 'Success';
    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts
        = [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    /** @var array Fields that can be filled */
    protected $fillable = ['status'];

    /**
     * @codeCoverageIgnore
     * @return bool
     */
    public function isAwaitingApproval(): bool
    {
        return self::AWAITING_APPROVAL === $this->status;
    }
    /**
     * @codeCoverageIgnore
     * @return bool
     */
    public function isAwaitingPayment(): bool
    {
        return self::AWAITING_PAYMENT === $this->status;
    }
    /**
     * @codeCoverageIgnore
     * @return bool
     */
    public function isDeclined(): bool
    {
        return self::DECLINED === $this->status;
    }
    /**
     * @codeCoverageIgnore
     * @return bool
     */
    public function isSuccess(): bool
    {
        return self::SUCCESS === $this->status;
    }

}
