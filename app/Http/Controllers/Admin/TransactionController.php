<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockTotal;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Midtrans\Config;
use Midtrans\Snap;


class TransactionController extends Controller
{
    /**
     * Menampilkan halaman transaksi dan data terkait.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        $orderId = $request->input('order_id');
        $statusCode = $request->input('status_code');
        $transactionStatus = $request->input('transaction_status');


        if ($orderId && $statusCode && $transactionStatus) {

            $transaction = Transaction::where('invoice', $orderId)->first();

            if ($transaction) {

                if ($statusCode == 200 && $transactionStatus == 'settlement') {
                    $transaction->status = 'success';
                } elseif ($transactionStatus == 'pending') {
                    $transaction->status = 'pending';
                } elseif ($transactionStatus == 'failed') {
                    $transaction->status = 'failed';
                }
                $transaction->save();
            }


            return redirect()->route('admin.sales.index');
        }

        $customers = Customer::all();
        $products = Product::all();
        $categories = Category::all();

        $carts = Cart::with('product')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'total_price' => $item->total_price,
                    'selling_price' => $item->product->selling_price,
                ];
            });


        $payment_link_url = $request->input('payment_link_url', null);


        return Inertia::render('Admin/Transactions/Index', [
            'customers' => $customers,
            'products' => $products,
            'categories' => $categories,
            'carts' => $carts,
            'payment_link_url' => $payment_link_url,
        ]);
    }

     /**
     * Menambahkan produk ke keranjang dengan validasi stok.
     */
    public function addProductToCart(Request $request)
    {
        $validatedData = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'total_price' => 'required|numeric|min:1000',
        ]);

        $userId = Auth::id();
        $customerId = $validatedData['customer_id'] ?? null;

        if (!$userId) {
            return redirect()->route('login');
        }

        // Ambil total stok produk berdasarkan product_id
        $productStock = StockTotal::where('product_id', $validatedData['product_id'])->value('total_stock');
        if (!$productStock || $productStock <= 0) {
            return redirect()->back()->withErrors(['quantity' => 'Stok produk masih 0.']);
        }

        // Jumlahkan kuantitas produk yang ada di keranjang
        $cartQuantity = Cart::where('user_id', $userId)
            ->where('product_id', $validatedData['product_id'])
            ->sum('quantity');
        $newQuantity = $cartQuantity + $validatedData['quantity'];

        // Validasi jika kuantitas melebihi stok
        if ($newQuantity > $productStock) {
            return redirect()->back()->withErrors(['quantity' =>
            'Kuantitas melebihi stok yang tersedia. Stok saat ini: ' . $productStock]);
        }

        // Cari item di cart berdasarkan user_id dan product_id
        $cartItem = Cart::where('user_id', $userId)
            ->where('product_id', $validatedData['product_id'])
            ->first();

        // Jika item ditemukan, update kuantitas dan total harga
        // Case 1 — Pada saat kasir menambahkan product ke cart tanpa pilih customer
        // $customerId = 5;
        // $cartItem->customer_id = null;
        // kondisi:
        // $customerId = true
        // null !== 5 = true
        //  if (true && null !== 5) { maka (true && true)
            // $cartItem->customer_id = 5;
        // }

        // Case 2, kondisi customer yang sama dan customer_id sudah ada di table cart :
        // $customerId = 5;
        // $cartItem->customer_id = 5;
        // 5 = true
        // $cartItem->customer_id !== $customerId
        // 5 !== 5 → false
        // hasil akhir
        // true && false maka false, maka hanya update quantity dan total_price
        // tanpa mengubah customer_id atau quer lagi ke database untuk update customer_id

        // Case 3 — Customer diganti
        // $customerId = 7;
        // $cartItem->customer_id = 5;
        // kondisi:
        // true && true
        // RESULT: berubah dari 5 → 7

        if ($cartItem) {
            if ($customerId && $cartItem->customer_id !== $customerId) {
                $cartItem->customer_id = $customerId;
            }
            $cartItem->quantity += $validatedData['quantity'];
            $cartItem->total_price += $validatedData['total_price'];
            $cartItem->save();
        } else {
            // Jika item tidak ditemukan, buat entry baru di tabel cart
            Cart::create([
                'user_id' => $userId,
                'customer_id' => $customerId,
                'product_id' => $validatedData['product_id'],
                'quantity' => $validatedData['quantity'],
                'total_price' => $validatedData['total_price'],
            ]);
        }

        return redirect()->back();
    }

    /**
    * Memproses pembayaran (cash/online) dan menghapus cart jika proses berhasil.
    */
    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            // min:0 tidak boleh -1 atau negatif
            // 'total_amount' => 'required|numeric',
            'cash' => 'nullable|numeric|min:0',
            'change' => 'nullable|numeric|min:0',
            // 'cart_items' => 'required|array',
            // 'cart_items.*.product_id' => 'required|exists:products,id',
            // 'cart_items.*.quantity' => 'required|integer|min:1',
            'discount' => 'nullable|numeric',
            'payment_method' => 'required|in:cash,online',
        ]);

        $discount = $validated['discount'] ?? 0;
        // $totalAmount = $validated['total_amount'];

        $snapToken = null;
        $userId = Auth::id();

        // kita ambil dari db, agar tidak ada manipulasi dari client side,
        // karena total_amount itu bisa dimanipulasi di client side,
        // maka kita hitung ulang di server side berdasarkan data cart
        // yang ada di database, agar lebih aman dan valid
        $carts = Cart::with('product')
            ->where('user_id', $userId)
            ->get();

        if ($carts->isEmpty()) {
            return back()->withErrors([
                'cart' => 'Cart masih kosong silahkan tambahkan produk ke cart sebelum melakukan pembayaran.',
            ]);
        }

        // hitung ulang subtotal dari db
        $subTotal = $carts->sum(function ($cart) {
            return $cart->product->selling_price * $cart->quantity;
        });

        // double validation in backend
        if ($discount > $subTotal) {
            return back()->withErrors([
                'discount' => "Diskon tidak boleh melebihi total belanja {$subTotal}.",
            ]);
        }

        $totalAmount = $subTotal - $discount;

        // data mappingan, makanya kita gunakan pluck
        // $validated['cart_items'] = [
        //     ['product_id' => 1, 'quantity' => 2],
        //     ['product_id' => 5, 'quantity' => 1],
        // ];

        try {
            // Gunakan DB transaction untuk memastikan data konsisten
            DB::transaction(function () use ($carts, $validated, $userId, $totalAmount, &$snapToken) {
                // create transaction
                $transaction = Transaction::create([
                    'customer_id' => $validated['customer_id'],
                    'user_id' => $userId,
                    'total_amount' => $totalAmount,
                    'cash' => $validated['payment_method'] === 'cash' ? $validated['cash'] : null,
                    'change' => $validated['payment_method'] === 'cash' ? $validated['change'] : null,
                    'discount' => $validated['discount'] ?? 0,
                    'payment_method' => $validated['payment_method'],
                    'status' => $validated['payment_method'] === 'online' ? 'pending' : 'success',
                ]);

                foreach ($carts as $cart) {
                    // lock per row (hindari race condition)
                    $stock = StockTotal::where('product_id', $cart->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock || $stock->total_stock < $cart->quantity) {
                        if (!$stock || $stock->total_stock < $cart->quantity) {
                            throw new \Exception(
                                "Stok produk untuk {$cart->product->name} tidak mencukupi. Stok saat ini: "
                                . ($stock->total_stock ?? 0)
                            );
                        }
                    }

                    // kurangi stock (atomic)
                    $stock->decrement('total_stock', $cart->quantity);

                    // simpan detail transaksi
                    $transaction->transactionDetails()->create([
                        'product_id' => $cart->product_id,
                        'product_name' => $cart->product->name,
                        'quantity' => $cart->quantity,
                        'subtotal' => $cart->quantity * $cart->product->selling_price,
                    ]);
                }

                // jika ada customer_id / kasir input pembeli
                if ($validated['customer_id']) {
                    Cart::where('user_id', $userId)
                        ->whereNull('customer_id')
                        ->update(['customer_id' => $validated['customer_id']]);

                    Cart::where('customer_id', $validated['customer_id'])
                        ->where('user_id', $userId)
                        ->delete();
                } else {
                    Cart::where('user_id', $userId)->delete();
                }

                // Jika online, buat transaksi Midtrans dan dapatkan Snap Token
                if ($validated['payment_method'] === 'online') {
                    $snapToken = $this->createMidtransTransaction($transaction);
                }
            });

            return redirect()->route('admin.sales.index');

        } catch (\Exception $e) {
            return back()->withErrors([
                'quantity' => $e->getMessage()
            ]);
        }
    }


     /**
     * Membuat Snap Token dari Midtrans.
     */
    protected function createMidtransTransaction($transaction)
    {
    }

     /**
     * Menghapus item dari keranjang berdasarkan ID.
     */
    public function deleteFromCart($id)
    {
        $cartItem = Cart::find($id);
        if ($cartItem) {
            $cartItem->delete();
            return redirect()->back();
        }
        return redirect()->back();
    }

}
