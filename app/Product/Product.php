<?php

namespace App\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $connection = 'mysql_cdb';
    protected $table = 'products';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'products_categories', 'product_id', 'category_id');
    }

    public function kemasan()
    {
        return $this->belongsTo(Kemasan::class, 'kemasan_id');
    }

    public function satuan()
    {
        return $this->belongsTo(Satuan::class, 'satuan_id');
    }

    public function hargas()
    {
        return $this->hasMany(Harga::class, 'product_id');
    }
    
    public function indexHargas()
    {
        return $this->hasMany(IndexHarga::class, 'product_id');
    }

    public function primaryHarga()
    {
        return $this->belongsTo(Harga::class, 'primary_harga_id');
    }

    public function scopeWithPrimaryHarga($query, $wilayahId)
    {
        $harga = Harga::select('id')
            ->whereColumn('hargas.product_id', 'products.id')
            ->active()
            ->where('wilayah_id', $wilayahId)
            ->orderBy('isdefault', 'desc')
            ->limit(1)
            ->getQuery();

        return $query->selectSub($harga, 'primary_harga_id');
    }

    public function pictures()
    {
        return $this->hasMany(Picture::class, 'product_id');
    }

    public function primaryPicture()
    {
        return $this->belongsTo(Picture::class, 'primary_picture_id');
    }

    public function scopeWithPrimaryPicture($query)
    {
        $picture = Picture::select('id')
            ->whereColumn('product_id', 'products.id')
            ->where('status', 1)
            ->limit(1)
            ->getQuery();

        return $query->selectSub($picture, 'primary_picture_id');
    }

    public function scopeWithDetail($query, $wilayahId)
    {
        $query->select(
            'products.id AS product_id',
            'hargas.id AS harga_id',
            'products.title',
            'productpictures.title AS image',
            'kemasans.title AS type',
            'kemasans.simbol',
            'products.berat_kemasan',
            'wilayahs.id AS wilayah_id',
            'wilayahs.title AS wilayah',
            DB::raw('IFNULL( hargas.min_qty, 1 ) AS min_qty'),
            'hargas.grade',
            'harga_grades.title AS grade_title',
            DB::raw('IFNULL(hargas.unitprice, 0) AS unitprice'),
            'wilayahs.delivery_time',
            'products.viewer',
            DB::raw('IFNULL(stocks.jumlah_stock, 0) AS stock'),
            'products.detail AS deskripsi',
            'products.cara_penyimpanan',
            'products.manfaat',
            'tm_labels.title AS label_produk',
            'products.tags',
        )
        ->leftJoin('hargas', function ($join) use ($wilayahId) {
            $join->on('hargas.product_id', '=', 'products.id')
                ->where('hargas.unitprice', '>', 0)
                ->where('hargas.min_qty', '>', 0)
                ->where('hargas.status', 1)
                ->where('hargas.wilayah_id', $wilayahId);
        })
        ->leftJoin('harga_grades', 'harga_grades.id', '=', 'hargas.grade')
        ->leftJoin('wilayahs', function ($join) {
            $join->on('wilayahs.id', '=', 'hargas.wilayah_id');
        })
        ->leftJoin('product_wilattrs', function ($join) use ($wilayahId) {
            $join->on('product_wilattrs.product_id', '=', 'products.id')
                ->where('product_wilattrs.is_cust', 1)
                ->where('product_wilattrs.publish', 1)
                ->where('product_wilattrs.wilayah_id', $wilayahId);
        })
        ->leftJoin('productpictures', function ($join) {
            $join->on('productpictures.product_id', '=', 'products.id')
                ->where('productpictures.status', 1);
        })
        ->leftJoin('stocks', function ($join) use ($wilayahId) {
            $join->on('stocks.product_id', '=', 'products.id')
                ->where('stocks.wilayah_id', $wilayahId);
        })
        ->leftJoin('kemasans', 'kemasans.id', '=', 'products.kemasan_id')
        ->leftJoin('tm_labels', 'tm_labels.id', 'products.label_id')
        ->where('products.status', 1)
        ->where('products.publish', 1)
        ->where('wilayahs.id', $wilayahId);
    }

    public function scopeWithMinimalDetailWhere($query, $wilayahId)
    {
        $query->select(
            'products.id AS product_id',
            'products.title',
            DB::raw('IFNULL(hargas.unitprice, 0) AS unitprice'),
            DB::raw("CONCAT('sm', productpictures.title) AS image"),
            'wilayahs.id AS wilayah_id',
            'wilayahs.title AS wilayah',
            DB::raw('IFNULL( hargas.min_qty, 1 ) AS min_qty'),
            'hargas.grade',
            'harga_grades.title AS grade_title',
            'categories_mapping.name AS category',
            'categories_mapping.categories_id AS category_id',
            'tm_labels.title AS label_produk',
            'products.tags',
        )
        ->leftJoin('hargas', function ($join) use ($wilayahId) {
            $join->on('hargas.product_id', '=', 'products.id')
                ->where('hargas.unitprice', '>', 0)
                ->where('hargas.min_qty', '>', 0)
                ->where('hargas.status', 1)
                ->where('hargas.wilayah_id', $wilayahId);
        })
        ->join('product_wilattrs', function ($join) use ($wilayahId) {
            $join->on('product_wilattrs.product_id', '=', 'products.id')
                ->where('product_wilattrs.publish', 1)
                ->where('product_wilattrs.wilayah_id', $wilayahId);
        })
        ->leftJoin('harga_grades', 'harga_grades.id', '=', 'hargas.grade')
        ->leftJoin('productpictures', function ($join) {
            $join->on('productpictures.product_id', '=', 'products.id')
                ->where('productpictures.status', 1);
        })
        ->leftJoin('wilayahs', function ($join) {
            $join->on('wilayahs.id', '=', 'hargas.wilayah_id');
        })
        ->leftJoin('products_categories', 'products_categories.product_id', 'products.id')
        ->join('categories', 'products_categories.category_id', 'categories.id')
        ->leftJoin('categories_mapping', 'categories_mapping.categories_id', 'categories.id')
        ->leftJoin('tm_labels', 'tm_labels.id', 'products.label_id')
        ->whereNotNull('categories.id')
        ->whereNotNull('categories_mapping.name')
        ->whereNotNull('categories_mapping.categories_id')
        ->where('products.status', 1)
        ->where('products.publish', 1)
        ->where('product_wilattrs.is_cust', 1)
        ->where('wilayahs.id', $wilayahId)
        ->distinct();
    }
}
