<?php

namespace App\Models;

use App\Enums\Environment;
use App\Enums\ImplStatus;
use App\Enums\SdkLanguage;
use Database\Factories\ToolImplementationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tool_contract_id
 * @property string $application_id
 * @property Environment $environment
 * @property ImplStatus $status
 * @property string|null $handler_name
 * @property string|null $implemented_version
 * @property SdkLanguage|null $language
 * @property Carbon|null $last_validated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ToolContract $toolContract
 * @property-read Application $application
 */
#[Fillable(['tool_contract_id', 'application_id', 'environment', 'status', 'handler_name', 'implemented_version', 'language', 'last_validated_at'])]
class ToolImplementation extends Model
{
    /** @use HasFactory<ToolImplementationFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the tool contract being implemented.
     *
     * @return BelongsTo<ToolContract, $this>
     */
    public function toolContract(): BelongsTo
    {
        return $this->belongsTo(ToolContract::class);
    }

    /**
     * Get the application reporting the implementation.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'environment' => Environment::class,
            'status' => ImplStatus::class,
            'language' => SdkLanguage::class,
            'last_validated_at' => 'datetime',
        ];
    }
}
