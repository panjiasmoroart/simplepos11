<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockOpnameRequest;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Models\Product;
use App\Models\StockTotal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Exports\StockOpnamesExport;
use Maatwebsite\Excel\Facades\Excel;

class StockOpnameController extends Controller
{
    /**
     * Tampilkan daftar semua stock opname.
     */
    public function index()
    {
        // Ambil data stock opname terbaru dan paginate
        $stockOpnames = StockOpname::latest()->paginate(10);

        // Kirim data ke komponen Inertia 'Admin/StockOpnames/Index'
        return inertia('Admin/StockOpnames/Index', [
            'stockOpnames' => $stockOpnames
        ]);
    }


    /**
     * Tampilkan form untuk membuat stock opname baru.
     */
    public function create()
    {
        // Ambil semua produk untuk dipilih dalam form
        $products = Product::all();

        // Kirim data ke Inertia 'Admin/StockOpnames/Create'
        return inertia('Admin/StockOpnames/Create', [
            'products' => $products
        ]);
    }

    /**
     * Simpan stock opname baru ke database.
     */
    public function store(StockOpnameRequest $request)
    {
        // Validasi data input melalui StockOpnameRequest
        $validatedData = $request->validated();

        // Ubah format tanggal ke 'Y-m-d'
        $opnameDate = Carbon::parse($validatedData['opname_date'])->format('Y-m-d');

        // Ambil semua product_id
        $productIds = collect($validatedData['products'])
            ->pluck('product_id')
            ->unique()
            ->values();

        // Ambil semua stock_total
        $stockTotals = StockTotal::whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

         // Ambil semua product (untuk error message)
        $products = Product::whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $detailsToInsert = [];
        $errors = [];

        try {
             DB::transaction(function () use ($validatedData, $products,  $stockTotals, $opnameDate, &$detailsToInsert, &$errors) {
                // Buat record StockOpname (header)
                $stockOpname = StockOpname::create([
                    'opname_date' => $opnameDate,
                    'status' => 'pending',
                ]);

                foreach ($validatedData['products'] as $index => $productData) {
                    $productId = $productData['product_id'];
                    $stockTotal = $stockTotals[$productId] ?? null;

                    if (!$stockTotal) {
                        $productName = $products[$productId]->name ?? 'Produk Tidak Ditemukan';
                        $errors["products.$index.product_id"] =
                            "Stok untuk produk \"{$productName}\" tidak ditemukan.";
                        continue;
                    }

                    $quantityDifference = $productData['physical_quantity'] - $stockTotal->total_stock;

                    $detailsToInsert[] = [
                        'stock_opname_id'     => $stockOpname->id,
                        'product_id'          => $productId,
                        'stock_total_id'      => $stockTotal->id,
                        'physical_quantity'   => $productData['physical_quantity'],
                        'quantity_difference' => $quantityDifference,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ];
                }

                if (!empty($errors)) {
                    throw ValidationException::withMessages($errors);
                }

                // bulk insert
                // StockOpnameDetail::insert($detailsToInsert);
                collect($detailsToInsert)->chunk(1000)->each(function ($chunk) {
                    StockOpnameDetail::insert($chunk->toArray());
                });
             });
        } catch (ValidationException $e) {
             // agar inertia dapet format asli
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Terjadi kesalahan pada server.'
            ]);
        }

        // Kembali ke daftar stock opname
        return redirect()->route('admin.stock-opnames.index');
    }

    public function storeOld(StockOpnameRequest $request)
    {
        // Validasi data input melalui StockOpnameRequest
        $validatedData = $request->validated();

        // Ubah format tanggal ke 'Y-m-d'
        $opnameDate = Carbon::parse($validatedData['opname_date'])->format('Y-m-d');

        // Buat record StockOpname (header)
        $stockOpname = StockOpname::create([
            'opname_date' => $opnameDate,
            'status' => 'pending',
        ]);

        // Simpan detail tiap produk yang diopname
        foreach ($validatedData['products'] as $productData) {
            $stockTotal = StockTotal::where('product_id', $productData['product_id'])->first();

            // Jika stok total tidak ada, kembalikan pesan error
            if (!$stockTotal) {
                // Ambil nama produk dari tabel `products`
                $productName = Product::find($productData['product_id'])?->name ?? 'Produk Tidak Ditemukan';

                return redirect()->back()->withErrors([
                    'products' => 'Stok untuk produk "' . $productName . '" tidak ditemukan.'
                ]);
            }

            // Hitung selisih antara stok fisik dengan stok total
            $quantityDifference = $productData['physical_quantity'] - $stockTotal->total_stock;

            // Buat detail stock opname
            $stockOpname->details()->create([
                'product_id' => $productData['product_id'],
                'stock_total_id' => $stockTotal->id,
                'physical_quantity' => $productData['physical_quantity'],
                'quantity_difference' => $quantityDifference,
            ]);
        }

        // Kembali ke daftar stock opname
        return redirect()->route('admin.stock-opnames.index');
    }

    /**
     * Tampilkan form untuk mengedit stock opname tertentu.
     */
    public function edit($id)
    {
        // Ambil stock opname beserta detail, produk, dan stok total terkait
        $stockOpname = StockOpname::with(['details.product', 'details.stockTotal'])->findOrFail($id);

        // Ambil semua produk untuk pilihan edit
        $products = Product::all();

        // Kirim data ke Inertia 'Admin/StockOpnames/Edit'
        return inertia('Admin/StockOpnames/Edit', [
            'stockOpname' => $stockOpname,
            'products' => $products
        ]);
    }

    /**
     * Update data stock opname di database.
     */
    public function updateOld(StockOpnameRequest $request, StockOpname $stockOpname)
    {
        // Validasi input
        $validatedData = $request->validated();

        // Format tanggal opname
        $opnameDate = Carbon::parse($validatedData['opname_date'])->format('Y-m-d');

        // Update bagian header stock opname (tanggal dan status)
        $stockOpname->update([
            'opname_date' => $opnameDate,
            'status' => $validatedData['status'],
        ]);

        // Hapus detail lama
        $stockOpname->details()->delete();

        // Simpan detail baru
        foreach ($validatedData['products'] as $productData) {
            $stockTotal = StockTotal::where('product_id', $productData['product_id'])->first();

            // Jika stok total tidak ditemukan, kembali dengan error
            if (!$stockTotal) {
                return redirect()->back()->withErrors([
                    'products' => 'Stok produk dengan ID ' . $productData['product_id'] . ' tidak ditemukan.'
                ]);
            }

            // Hitung selisih antara stok fisik dengan stok total
            $quantityDifference = $productData['physical_quantity'] - $stockTotal->total_stock;

            // Buat record detail baru
            $stockOpname->details()->create([
                'product_id' => $productData['product_id'],
                'stock_total_id' => $stockTotal->id,
                'physical_quantity' => $productData['physical_quantity'],
                'quantity_difference' => $quantityDifference,
            ]);
        }

        // Kembali ke daftar stock opname
        return redirect()->route('admin.stock-opnames.index');
    }

    public function update(StockOpnameRequest $request, StockOpname $stockOpname)
    {
        $validatedData = $request->validated();

        $opnameDate = Carbon::parse($validatedData['opname_date'])->format('Y-m-d');

        $productIds = collect($validatedData['products'])
            ->pluck('product_id')
            ->unique();

        $stockTotals = StockTotal::whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $errors = [];
        $detailsToInsert = [];

        try {
            DB::transaction(function () use (
                $stockOpname,
                $validatedData,
                $opnameDate,
                $stockTotals,
                &$errors,
                &$detailsToInsert
            ) {
                // Update header
                $stockOpname->update([
                    'opname_date' => $opnameDate,
                    'status' => $validatedData['status'],
                ]);

                foreach ($validatedData['products'] as $index => $productData) {
                    $productId = $productData['product_id'];
                    $stockTotal = $stockTotals[$productId] ?? null;

                    if (!$stockTotal) {
                        $errors["products.$index.product_id"] =
                            "Stok untuk product ID {$productId} tidak ditemukan.";
                        continue;
                    }

                    $detailsToInsert[] = [
                        'stock_opname_id'     => $stockOpname->id,
                        'product_id'          => $productId,
                        'stock_total_id'      => $stockTotal->id,
                        'physical_quantity'   => $productData['physical_quantity'],
                        'quantity_difference' => $productData['physical_quantity'] - $stockTotal->total_stock,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ];
                }

                if (!empty($errors)) {
                    throw ValidationException::withMessages($errors);
                }

                // Hapus DetailsOpname lama
                $stockOpname->details()->delete();

                // Bulk insert
                collect($detailsToInsert)->chunk(1000)->each(function ($chunk) {
                    StockOpnameDetail::insert($chunk->toArray());
                });
            });

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Terjadi kesalahan pada server.'
            ]);
        }

        return redirect()->route('admin.stock-opnames.index');
    }

    /**
     * Tampilkan detail stock opname tertentu.
     */
    public function show(StockOpname $stockOpname)
    {
        // eager loading relasi setelah model sudah diambil dari database.
        // nested relationship:

        // details → relasi dari StockOpname ke StockOpnameDetail
        // product → relasi dari StockOpnameDetail ke Product
        // stockTotal → relasi dari StockOpnameDetail ke StockTotal

        // Muat detail produk dan stok total yang terkait
        $stockOpname->load('details.product', 'details.stockTotal');

        // Kirim data ke Inertia 'Admin/StockOpnames/Show'
        return inertia('Admin/StockOpnames/Show', [
            'stockOpname' => $stockOpname,
        ]);
    }

    // Method untuk mengekspor data Stock Opname ke Excel
    public function export($id)
    {
        // Mengambil data StockOpname bersama dengan detail produk dan jumlah stok
        $stockOpname = StockOpname::with(['details.product', 'details.stockTotal'])->findOrFail($id);

        // Membungkus StockOpname ke dalam array jika diperlukan oleh eksport
        $export = new StockOpnamesExport([$stockOpname]);

        // Menggunakan Excel facade untuk men-download file Excel
        return Excel::download($export, 'stock_opname_' . $id . '.xlsx');
    }

}
