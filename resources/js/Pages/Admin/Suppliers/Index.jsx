import React, { useState, useEffect, useMemo, useCallback } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import Swal from "sweetalert2";
import Pagination from "../../../Components/Pagination";
import AdminLayout from "../../../Layouts/AdminLayout";
import hasAnyPermission from "../../../utils/hasAnyPermission";
import debounce from "lodash.debounce";

export default function SupplierIndex() {
  const { suppliers, filters } = usePage().props;

  const [search, setSearch] = useState(filters.q || "");
  const [status, setStatus] = useState(filters.status || "");
  const [perPage, setPerPage] = useState(filters.per_page || 10);

  const [isLoading, setIsLoading] = useState(false);
  const [hasMounted, setHasMounted] = useState(false);

  // single source of truth (no race)
  const fetchData = useCallback((params) => {
    setIsLoading(true);

    router.get("/admin/suppliers", params, {
      preserveState: true,
      replace: true,
      onFinish: () => setIsLoading(false),
    });
  }, []);

  // debounce stable
  const debouncedSearch = useMemo(() => debounce(fetchData, 400), [fetchData]);

  // trigger debounce
  useEffect(() => {
    if (!hasMounted) {
      setHasMounted(true);
      return;
    }

    debouncedSearch({
      q: search,
      status,
      per_page: perPage,
    });
  }, [search, status, perPage]);

  // cleanup
  useEffect(() => {
    return () => debouncedSearch.cancel();
  }, [debouncedSearch]);

  // delete (clean & safe)
  const handleDelete = (id) => {
    Swal.fire({
      title: "Are you sure?",
      text: "Data will be deleted permanently!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      confirmButtonText: "Yes, delete it!",
    }).then((result) => {
      if (!result.isConfirmed) return;

      // stop debounce biar gak bentrok
      debouncedSearch.cancel();

      setIsLoading(true);

      router.delete(`/admin/suppliers/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire("Deleted!", "Supplier deleted.", "success");

          // refetch pakai state sekarang
          fetchData({
            q: search,
            status,
            per_page: perPage,
          });
        },
        onError: () => {
          setIsLoading(false);
          Swal.fire("Error!", "Delete failed.", "error");
        },
      });
    });
  };

  return (
    <>
      <Head title="Suppliers - SimplePOS" />

      <AdminLayout>
        <nav aria-label="breadcrumb">
          <ol className="breadcrumb">
            <li className="breadcrumb-item">
              <Link href="/admin">Dashboard</Link>
            </li>
            <li className="breadcrumb-item active" aria-current="page">
              Suppliers
            </li>
          </ol>
        </nav>
        <div className="row mb-3">
          <div className="col-md-12">
            <div className="d-flex justify-content-between align-items-center">
              <h3 className="font-weight-bold">
                <i className="bi bi-truck"></i> Suppliers
              </h3>

              {hasAnyPermission(["suppliers.create"]) && (
                <Link
                  href="/admin/suppliers/create"
                  className="btn btn-success"
                >
                  <i className="bi bi-plus-circle-fill me-2"></i>
                  Add Supplier
                </Link>
              )}
            </div>
          </div>
        </div>

        <div className="row mb-3">
          <div className="col-md-12">
            <div className="d-flex flex-wrap justify-content-between align-items-center gap-2">
              {/* LEFT SIDE */}
              <div className="d-flex align-items-center gap-2 flex-wrap">
                <span>Show</span>

                <select
                  className="form-select"
                  style={{ width: "80px" }}
                  value={perPage}
                  onChange={(e) => setPerPage(Number(e.target.value))}
                >
                  {[10, 20, 30, 40, 50].map((data) => (
                    <option key={data} value={data}>
                      {data}
                    </option>
                  ))}
                </select>

                <span>entries</span>

                <select
                  className="form-select"
                  style={{ width: "150px" }}
                  value={status}
                  onChange={(e) => setStatus(e.target.value)}
                >
                  <option value="">All</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>

              {/* RIGHT SIDE */}
              <input
                type="text"
                className="form-control"
                style={{ maxWidth: "250px" }}
                placeholder="Search..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
          </div>
        </div>

        <div className="row">
          <div className="col-12">
            <div className="card border rounded">
              <div className="card-body p-0">
                <div className="table-responsive p-0">
                  <div className="position-relative">
                    {isLoading && (
                      <div
                        className="position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center"
                        style={{
                          backgroundColor: "rgba(255,255,255,0.6)",
                          zIndex: 10,
                        }}
                      >
                        <div className="spinner-border" role="status" />
                      </div>
                    )}

                    <table className="table align-middle table-hover">
                      <thead className="bg-light text-white">
                        <tr>
                          <th className="text-center">No.</th>
                          <th>Name</th>
                          <th>Phone</th>
                          <th>Address</th>
                          <th>Status</th>
                          <th className="text-center">Actions</th>
                        </tr>
                      </thead>

                      <tbody style={{ opacity: isLoading ? 0.5 : 1 }}>
                        {suppliers.data.length > 0 ? (
                          suppliers.data.map((supplier, index) => (
                            <tr key={supplier.id}>
                              <td className="text-center">
                                {index +
                                  1 +
                                  (suppliers.current_page - 1) *
                                    suppliers.per_page}
                              </td>
                              <td>{supplier.name || "No name available"}</td>
                              <td>{supplier.phone || "No phone available"}</td>
                              <td>
                                {supplier.address || "No address available"}
                              </td>
                              <td>
                                <span
                                  className={`badge ${
                                    supplier.status === "active"
                                      ? "bg-success"
                                      : "bg-secondary"
                                  }`}
                                >
                                  {supplier.status.charAt(0).toUpperCase() +
                                    supplier.status.slice(1)}
                                </span>
                              </td>
                              <td className="text-center">
                                {hasAnyPermission(["suppliers.edit"]) && (
                                  <Link
                                    href={`/admin/suppliers/${supplier.id}/edit`}
                                    className="btn btn-outline-primary btn-sm me-2 rounded"
                                  >
                                    <i className="bi bi-pencil-fill"></i> Edit
                                  </Link>
                                )}
                                {hasAnyPermission(["suppliers.delete"]) && (
                                  <button
                                    disabled={isLoading}
                                    className="btn btn-outline-danger btn-sm rounded"
                                    onClick={() => handleDelete(supplier.id)}
                                  >
                                    <i className="bi bi-trash-fill"></i> Delete
                                  </button>
                                )}
                              </td>
                            </tr>
                          ))
                        ) : (
                          <tr>
                            <td colSpan="6" className="text-center">
                              No suppliers found
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>
                <div className="d-flex justify-content-between align-items-center p-3">
                  <small>
                    Showing {suppliers.from} to {suppliers.to} of{" "}
                    {suppliers.total}
                  </small>

                  <Pagination links={suppliers.links} />
                </div>
              </div>
            </div>
          </div>
        </div>
      </AdminLayout>
    </>
  );
}
