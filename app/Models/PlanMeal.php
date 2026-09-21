<?php

namespace App\Models;

use App\Models\Concerns\HasOwnedImages;
use App\Support\PlanPhotoUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanMeal extends Model
{
    /** A picture of the meal, from the captain. Same rule as the exercise. */
    use HasOwnedImages;

    public const TYPES = ['breakfast', 'lunch', 'dinner', 'snack'];

    protected $fillable = [
        'training_plan_id',
        'meal_type',
        'name',
        'calories',
        'notes',
        'sort_order',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }

    /** Plan photos are private to the trainer and the trainee: signed links only. */
    protected function imageAddress(Image $image): string
    {
        return PlanPhotoUrl::for($image);
    }
}
