import React from "react";
import { useForm, usePage, Head } from "@inertiajs/react";
import Swal from "sweetalert2";
import AdminLayout from "../../../Layouts/AdminLayout";

export default function StockOpnameCreate() {
  const { products } = usePage().props;

  const { data, setData, post, processing, reset, errors } = useForm({
    opname_date: "",
    status: "pending",
    products: products.map((product) => ({
      product_id: product.id,
      physical_quantity: 0,
    })),
    // opname_date: "2026-04-22",
    // status: "pending",
    // products: [
    //   { product_id: -1, physical_quantity: 93 },
    //   { product_id: 2, physical_quantity: 47 },
    //   { product_id: 3, physical_quantity: 96 }, // ❌ invalid dan tidak ada di DB
    // ],
  });

  const handleReset = () => {
    setData({
      opname_date: "",
      products: products.map((product) => ({
        product_id: product.id,
        physical_quantity: 0,
      })),
    });
  };

  // const handleInjectDummy = () => {
  //   setData({
  //     opname_date: "2026-04-22",
  //     status: "pending",
  //     products: [
  //       { product_id: 1, physical_quantity: 93 },
  //       { product_id: 2, physical_quantity: 47 },
  //       { product_id: -3, physical_quantity: 96 }, // ❌ invalid dan tidak ada di DB
  //     ],
  //   });
  // };

  const handleSubmit = (e) => {
    e.preventDefault();
    // console.log("Submitting data:", data);
    // return;

    post("/admin/stock-opnames", {
      onSuccess: () => {
        Swal.fire({
          title: "Success!",
          text: "Stock opname created successfully!",
          icon: "success",
          showConfirmButton: false,
          timer: 1500,
        });
        reset();
      },
      onError: (errors) => {
        // const productErrors = Object.keys(errors)
        //   .filter((key) => key.startsWith("products"))
        //   .map((key) => errors[key]);
        // const productErrors = Object.entries(errors)
        //   .filter(([key]) => key.startsWith("products."))
        //   .map(([, value]) => value);
        const productErrors = Object.entries(errors)
          .filter(([key]) => key.startsWith("products."))
          .map(([key, value]) => {
            // parsing string ini sebagai bilangan desimal (base 10) -> 123
            // parseInt bisa membaca angka dalam berbagai basis, biner 2 -> 1010, heksadesimal 16 -> 1A
            const index = parseInt(key.split(".")[1], 10);
            return `Baris ${index + 1}: ${value}`;
          });

        if (productErrors.length > 0) {
          Swal.fire({
            title: "Error Product!",
            // text: productErrors.join(", "),
            html: `<ul style="text-align:left;">
              ${productErrors.map((e) => `<li>${e}</li>`).join("")}
            </ul>`,
            icon: "error",
          });
        }
      },
    });
  };

  const handleChange = (e) => {
    setData(e.target.name, e.target.value);
  };

  const handleProductChange = (index, field, value) => {
    const updatedProducts = [...data.products];
    updatedProducts[index][field] = value;
    setData("products", updatedProducts);
  };

  const handleBack = () => {
    window.history.back();
  };

  return (
    <>
      <Head>
        <title>Create Stock Opname - SimplePOS</title>
      </Head>
      <AdminLayout>
        <div className="container-fluid">
          <div className="row justify-content-center">
            <div className="col-md-8 col-lg-6">
              <div className="card border rounded shadow border-top-success">
                <div className="card-header d-flex justify-content-between align-items-center">
                  <button
                    onClick={handleBack}
                    className="btn btn-sm btn-secondary"
                  >
                    <i className="fa fa-arrow-left"></i> Back
                  </button>
                  <span className="font-weight-bold">
                    <i className="bi bi-clipboard"></i> Add New Stock Opname
                  </span>
                </div>
                <div className="card-body">
                  <form onSubmit={handleSubmit}>
                    {/* Input untuk Tanggal Opname */}
                    <div className="mb-3">
                      <label className="form-label fw-bold">Opname Date</label>
                      <input
                        type="date"
                        name="opname_date"
                        className={`form-control ${
                          errors.opname_date ? "is-invalid" : ""
                        }`}
                        value={data.opname_date}
                        onChange={handleChange}
                      />
                      {errors.opname_date && (
                        <div className="alert alert-danger">
                          {errors.opname_date}
                        </div>
                      )}
                    </div>

                    {/* Error umum untuk products */}
                    {errors.products && (
                      <div className="alert alert-danger">
                        {Array.isArray(errors.products)
                          ? errors.products.join(", ")
                          : errors.products}
                      </div>
                    )}

                    {/* Daftar Produk */}
                    <div className="mt-4">
                      <h5 className="font-weight-bold">Products</h5>
                      {data.products.map((item, index) => {
                        const displayIndex = index + 1;

                        return (
                          <div
                            key={item.product_id}
                            className="form-group mt-3"
                          >
                            <label>
                              {displayIndex}. {products[index].name}
                            </label>
                            <input
                              type="number"
                              className={`form-control ${
                                errors[`products.${index}.physical_quantity`]
                                  ? "is-invalid"
                                  : ""
                              }`}
                              value={item.physical_quantity}
                              onChange={(e) =>
                                handleProductChange(
                                  index,
                                  "physical_quantity",
                                  e.target.value,
                                )
                              }
                            />

                            {/* Error product_id */}
                            {errors[`products.${index}.product_id`] && (
                              <div className="text-danger small mt-1">
                                Baris {displayIndex}:{" "}
                                {errors[`products.${index}.product_id`]}
                              </div>
                            )}

                            {/* Error quantity */}
                            {errors[`products.${index}.physical_quantity`] && (
                              <div className="text-danger small mt-1">
                                Baris {displayIndex}:{" "}
                                {errors[`products.${index}.physical_quantity`]}
                              </div>
                            )}
                          </div>
                        );
                      })}
                    </div>

                    {/* Tombol Submit dan Reset */}
                    <div className="d-flex justify-content-end mt-4">
                      {/* <button
                        type="button"
                        className="btn btn-info me-2"
                        onClick={handleInjectDummy}
                      >
                        Inject Dummy
                      </button> */}

                      <button
                        type="submit"
                        className="btn btn-success me-2"
                        disabled={processing}
                      >
                        {processing ? (
                          <>
                            <div
                              className="spinner-border spinner-border-sm text-light me-2"
                              role="status"
                            >
                              <span className="visually-hidden">
                                Loading...
                              </span>
                            </div>
                            loading...
                          </>
                        ) : (
                          <>
                            <i className="fa fa-save"></i> Save
                          </>
                        )}
                      </button>
                      <button
                        type="button"
                        className="btn btn-warning"
                        onClick={handleReset}
                      >
                        <i className="fa fa-redo"></i> Reset
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </AdminLayout>
    </>
  );
}
