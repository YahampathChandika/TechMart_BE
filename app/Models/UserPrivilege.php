<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPrivilege extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'can_add_products',
        'can_update_products',
        'can_delete_products',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'can_add_products' => 'boolean',
        'can_update_products' => 'boolean',
        'can_delete_products' => 'boolean',
    ];

    /**
     * Relationship: Privilege belongs to user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create default privileges for user (all false)
     */
    public static function createDefaultForUser($userId)
    {
        return self::create([
            'user_id' => $userId,
            'can_add_products' => false,
            'can_update_products' => false,
            'can_delete_products' => false,
        ]);
    }

    /**
     * Grant all privileges to user
     */
    public function grantAllPrivileges()
    {
        $this->update([
            'can_add_products' => true,
            'can_update_products' => true,
            'can_delete_products' => true,
        ]);
    }

    /**
     * Revoke all privileges from user
     */
    public function revokeAllPrivileges()
    {
        $this->update([
            'can_add_products' => false,
            'can_update_products' => false,
            'can_delete_products' => false,
        ]);
    }

    /**
     * Update specific privilege
     */
    public function updatePrivilege($privilege, $value)
    {
        $allowedPrivileges = ['can_add_products', 'can_update_products', 'can_delete_products'];
        
        if (in_array($privilege, $allowedPrivileges)) {
            $this->update([$privilege => $value]);
        }
    }

    /**
     * Check if user has any privileges
     */
    public function hasAnyPrivilege()
    {
        return $this->can_add_products || 
               $this->can_update_products || 
               $this->can_delete_products;
    }

    /**
     * Get list of granted privileges
     */
    public function getGrantedPrivilegesAttribute()
    {
        $privileges = [];
        
        if ($this->can_add_products) {
            $privileges[] = 'can_add_products';
        }
        if ($this->can_update_products) {
            $privileges[] = 'can_update_products';
        }
        if ($this->can_delete_products) {
            $privileges[] = 'can_delete_products';
        }
        
        return $privileges;
    }

    /**
     * Scope: Users with specific privilege
     */
    public function scopeWithPrivilege($query, $privilege)
    {
        return $query->where($privilege, true);
    }
}