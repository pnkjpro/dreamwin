<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResponseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'node_id' => $this->node_id,
            'score' => $this->score,
            'status' => $this->status,
            'quiz_variant_id' => $this->quiz_variant_id,
            'quiz' => $this->when($this->quiz, [
                'title' => $this->quiz->title,
                'start_time' => $this->quiz->start_time,
                'end_time' => $this->quiz->end_time,
            ]),
        ];
    }
}
