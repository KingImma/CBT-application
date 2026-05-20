<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'max_students' => $this->max_students,
            'max_teachers' => $this->max_teachers,
            'max_exams_per_term' => $this->max_exams_per_term,
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'features' => $this->features,
            'is_active' => $this->is_active,
        ];
    }
}
