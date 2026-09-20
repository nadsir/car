<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'status',
        'published_at',
        'meta_title',
        'meta_description',
        'canonical_url',
        'is_featured',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
    ];

    /**
     * Author of the article
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Categories
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'article_category')->withTimestamps();
    }

    /**
     * Products
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'article_product')->withTimestamps();
    }

    /**
     * Vehicle engines (for vehicle compatibility)
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(VehicleEngine::class, 'article_vehicle')->withTimestamps();
    }

    /**
     * Vehicle brands
     */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(VehicleBrand::class, 'article_brand')->withTimestamps();
    }
}