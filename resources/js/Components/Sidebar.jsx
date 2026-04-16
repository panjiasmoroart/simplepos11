import React from "react";
import { Link } from "@inertiajs/react";
import NavItem from "./NavItem";
import hasAnyPermission from "../utils/hasAnyPermission";

const Sidebar = () => {
  return (
    <nav
      className="navbar sidebar navbar-expand-xl navbar-light bg-dark"
      style={{ overflowY: "auto" }}
    >
      <div className="d-flex align-items-center p-3">
        <Link className="navbar-brand" href="/">
          <span className="navbar-brand-item h5 text-primary mb-0">
            SimplePOS
          </span>
        </Link>
      </div>

      <div
        className="offcanvas offcanvas-start flex-row custom-scrollbar h-100"
        data-bs-backdrop="true"
        tabIndex="-1"
        id="offcanvasSidebar"
      >
        <div className="offcanvas-body sidebar-content d-flex flex-column bg-dark">
          <ul className="navbar-nav flex-column" id="navbar-sidebar">
            <li className="nav-item mt-3 mb-1 text-muted">Dashboard</li>
            {hasAnyPermission(["dashboard.index"]) && (
              <NavItem
                href="/admin/dashboard"
                icon="bi-speedometer"
                label="Dashboard"
              />
            )}

            <li className="nav-item mt-3 mb-1 text-muted">User Management</li>

            {hasAnyPermission(["roles.index"]) && (
              <NavItem
                href="/admin/roles"
                icon="bi-shield-lock"
                label="Roles"
              />
            )}

            {hasAnyPermission(["users.index"]) && (
              <NavItem
                href="/admin/users"
                icon="bi-person-circle"
                label="Users"
              />
            )}

            <li className="nav-item mt-3 mb-1 text-muted">Data Management</li>

            {hasAnyPermission(["suppliers.index"]) && (
              <NavItem
                href="/admin/suppliers"
                icon="bi-truck"
                label="Suppliers"
              />
            )}

            {hasAnyPermission(["customers.index"]) && (
              <NavItem
                href="/admin/customers"
                icon="bi-people"
                label="Customers"
              />
            )}

            {hasAnyPermission(["categories.index"]) && (
              <NavItem
                href="/admin/categories"
                icon="bi-list-ul"
                label="Categories"
              />
            )}
          </ul>
        </div>
      </div>
    </nav>
  );
};

export default Sidebar;
