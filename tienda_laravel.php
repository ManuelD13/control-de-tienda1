<?php

// ============================================
// MIGRACIONES (database/migrations)
// ============================================

// create_categories_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('categories');
    }
}

// create_products_table.php
class CreateProductsTable extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2);
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(5);
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('image')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
}

// create_customers_table.php
class CreateCustomersTable extends Migration
{
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('document')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('customers');
    }
}

// create_sales_table.php
class CreateSalesTable extends Migration
{
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            $table->enum('payment_method', ['cash', 'card', 'transfer']);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
}

// create_sale_items_table.php
class CreateSaleItemsTable extends Migration
{
    public function up()
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sale_items');
    }
}

// ============================================
// MODELOS (app/Models)
// ============================================

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'description'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

class Product extends Model
{
    protected $fillable = [
        'name', 'code', 'description', 'price', 'cost', 
        'stock', 'min_stock', 'category_id', 'image', 'active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isLowStock()
    {
        return $this->stock <= $this->min_stock;
    }
}

class Customer extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'address', 'document'];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}

class Sale extends Model
{
    protected $fillable = [
        'invoice_number', 'customer_id', 'user_id', 
        'subtotal', 'tax', 'discount', 'total', 
        'status', 'payment_method', 'notes'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}

class SaleItem extends Model
{
    protected $fillable = ['sale_id', 'product_id', 'quantity', 'price', 'subtotal'];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

// ============================================
// CONTROLADORES (app/Http/Controllers)
// ============================================

namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')
            ->orderBy('name')
            ->paginate(15);
        return view('products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        return view('products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:products',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($validated);

        return redirect()->route('products.index')
            ->with('success', 'Producto creado exitosamente');
    }

    public function edit(Product $product)
    {
        $categories = Category::orderBy('name')->get();
        return view('products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:products,code,' . $product->id,
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|max:2048',
            'active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        return redirect()->route('products.index')
            ->with('success', 'Producto actualizado exitosamente');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')
            ->with('success', 'Producto eliminado exitosamente');
    }

    public function lowStock()
    {
        $products = Product::whereColumn('stock', '<=', 'min_stock')
            ->with('category')
            ->get();
        return view('products.low-stock', compact('products'));
    }
}

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::with(['customer', 'user', 'items'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return view('sales.index', compact('sales'));
    }

    public function create()
    {
        $products = Product::where('active', true)
            ->where('stock', '>', 0)
            ->with('category')
            ->get();
        $customers = Customer::orderBy('name')->get();
        return view('sales.create', compact('products', 'customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,card,transfer',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $subtotal = 0;
        $items = [];

        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            
            if ($product->stock < $item['quantity']) {
                return back()->withErrors([
                    'items' => "Stock insuficiente para {$product->name}"
                ]);
            }

            $itemSubtotal = $product->price * $item['quantity'];
            $subtotal += $itemSubtotal;

            $items[] = [
                'product' => $product,
                'quantity' => $item['quantity'],
                'price' => $product->price,
                'subtotal' => $itemSubtotal,
            ];
        }

        $discount = $validated['discount'] ?? 0;
        $tax = ($subtotal - $discount) * 0.12; // 12% IVA
        $total = $subtotal - $discount + $tax;

        $sale = Sale::create([
            'invoice_number' => 'INV-' . str_pad(Sale::count() + 1, 6, '0', STR_PAD_LEFT),
            'customer_id' => $validated['customer_id'],
            'user_id' => auth()->id(),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'payment_method' => $validated['payment_method'],
            'notes' => $validated['notes'],
            'status' => 'completed',
        ]);

        foreach ($items as $item) {
            $sale->items()->create([
                'product_id' => $item['product']->id,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
            ]);

            $item['product']->decrement('stock', $item['quantity']);
        }

        return redirect()->route('sales.show', $sale)
            ->with('success', 'Venta registrada exitosamente');
    }

    public function show(Sale $sale)
    {
        $sale->load(['customer', 'user', 'items.product']);
        return view('sales.show', compact('sale'));
    }

    public function report(Request $request)
    {
        $query = Sale::with(['customer', 'items']);

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $sales = $query->orderBy('created_at', 'desc')->get();
        
        $totalSales = $sales->sum('total');
        $totalItems = $sales->sum(function($sale) {
            return $sale->items->sum('quantity');
        });

        return view('sales.report', compact('sales', 'totalSales', 'totalItems'));
    }
}

class DashboardController extends Controller
{
    public function index()
    {
        $todaySales = Sale::whereDate('created_at', today())->sum('total');
        $monthSales = Sale::whereMonth('created_at', now()->month)->sum('total');
        $lowStockProducts = Product::whereColumn('stock', '<=', 'min_stock')->count();
        $totalProducts = Product::count();
        
        $recentSales = Sale::with(['customer', 'items'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $topProducts = Product::withCount(['saleItems as total_sold' => function($query) {
            $query->select(\DB::raw('sum(quantity)'));
        }])
        ->orderBy('total_sold', 'desc')
        ->limit(5)
        ->get();

        return view('dashboard', compact(
            'todaySales', 'monthSales', 'lowStockProducts', 
            'totalProducts', 'recentSales', 'topProducts'
        ));
    }
}

// ============================================
// RUTAS (routes/web.php)
// ============================================

use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\DashboardController;

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    Route::resource('products', ProductController::class);
    Route::get('products/low-stock/list', [ProductController::class, 'lowStock'])->name('products.low-stock');
    
    Route::resource('sales', SaleController::class);
    Route::get('sales/report/generate', [SaleController::class, 'report'])->name('sales.report');
});

?>