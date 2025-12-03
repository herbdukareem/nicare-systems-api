<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ServiceBundle
 * Defines the composition and fixed price of a standard bundled service.
 */
class ServiceBundle extends Model
{
    protected $guarded = ['id'];
    protected $table = 'service_bundles';

    /**
     * A Bundle has many components (services, drugs, labs).
     */
    public function components()
    {
        return $this->hasMany(BundleComponent::class);
    }
}