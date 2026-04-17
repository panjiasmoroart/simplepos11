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
}
