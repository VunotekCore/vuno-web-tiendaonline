(function () {
  const TOKEN_KEY = "vuno_customer_token";
  const CUSTOMER_KEY = "vuno_customer_data";

  function getToken() {
    return localStorage.getItem(TOKEN_KEY);
  }

  function setToken(token) {
    if (token) {
      localStorage.setItem(TOKEN_KEY, token);
    } else {
      localStorage.removeItem(TOKEN_KEY);
    }
  }

  function getStoredCustomer() {
    try {
      const raw = localStorage.getItem(CUSTOMER_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  }

  function setStoredCustomer(customer) {
    if (customer) {
      localStorage.setItem(CUSTOMER_KEY, JSON.stringify(customer));
    } else {
      localStorage.removeItem(CUSTOMER_KEY);
    }
  }

  async function authFetch(url, options = {}) {
    const token = getToken();
    const headers = options.headers || {};
    if (token) {
      headers["Authorization"] = "Bearer " + token;
    }
    return fetch(url, { ...options, headers });
  }

  async function verify() {
    const token = getToken();
    if (!token) return null;
    try {
      const res = await authFetch("/api/customer/verify.php");
      if (!res.ok) {
        setToken(null);
        setStoredCustomer(null);
        dispatchAuthEvent(null);
        return null;
      }
      const data = await res.json();
      if (data.customer) {
        setStoredCustomer(data.customer);
        dispatchAuthEvent(data.customer);
        return data.customer;
      }
      return null;
    } catch {
      return null;
    }
  }

  async function login(email, password) {
    const res = await fetch("/api/customer/login.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || "Login failed");
    setToken(data.token);
    setStoredCustomer(data.customer);
    dispatchAuthEvent(data.customer);
    return data;
  }

  async function register(name, email, password) {
    const res = await fetch("/api/customer/register.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, email, password }),
    });
    let data;
    try {
      data = await res.json();
    } catch {
      throw new Error("Error del servidor. Intenta de nuevo.");
    }
    if (!res.ok) throw new Error(data.error || "Registration failed");
    setToken(data.token);
    setStoredCustomer(data.customer);
    dispatchAuthEvent(data.customer);
    return data;
  }

  async function logout() {
    const token = getToken();
    if (token) {
      try {
        await authFetch("/api/customer/logout.php", { method: "POST" });
      } catch {}
    }
    setToken(null);
    setStoredCustomer(null);
    dispatchAuthEvent(null);
  }

  function getCustomer() {
    return getStoredCustomer();
  }

  function isLoggedIn() {
    return !!getToken();
  }

  function dispatchAuthEvent(customer) {
    window.dispatchEvent(new CustomEvent("auth:changed", { detail: { customer } }));
  }

  // Remove token if expired
  async function checkOnLoad() {
    const token = getToken();
    if (!token) {
      dispatchAuthEvent(null);
      return;
    }
    await verify();
  }

  window.VunoAuth = {
    login,
    register,
    logout,
    verify,
    getCustomer,
    isLoggedIn,
    getToken,
    authFetch,
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", checkOnLoad);
  } else {
    checkOnLoad();
  }
})();
