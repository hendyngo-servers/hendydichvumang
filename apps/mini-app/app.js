(() => {
  "use strict";
  const tg = window.Telegram?.WebApp;
  const $ = (selector) => document.querySelector(selector);
  const apiBase = document.querySelector('meta[name="worker-api-url"]')?.content?.trim().replace(/\/$/, "") || "";
  const serviceText = {
    otp: "Dịch vụ OTP chưa kết nối nhà cung cấp.",
    proxy: "Proxy tĩnh và Proxy xoay đang chờ cấu hình.",
    vip: "Kho VIP chưa kết nối hệ thống cấp quyền.",
    code: "Kho Code chưa kết nối dữ liệu sản phẩm.",
    rental: "Thuê Tool đang chờ kết nối dịch vụ.",
    live: "Minigame Live đang chờ tích hợp."
  };
  let authenticated = false;
  let initData = "";
  let toastTimer;

  if (tg) {
    tg.ready();
    tg.expand();
    tg.setHeaderColor?.("#080d1c");
    tg.setBackgroundColor?.("#080d1c");
  }
  $("#year").textContent = String(new Date().getFullYear());

  function toast(message) {
    const node = $("#toast");
    node.textContent = message;
    node.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => node.classList.remove("show"), 2600);
  }
  function setSession(label, status) {
    $("#session-state").textContent = label;
    $("#auth-status").textContent = status;
  }
  async function post(path, payload) {
    if (!apiBase || apiBase.includes("__WORKER_API_URL__")) throw new Error("WORKER_API_URL chưa được cấu hình.");
    const response = await fetch(apiBase + path, {
      method: "POST", headers: { "content-type": "application/json" }, body: JSON.stringify(payload)
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) throw new Error(data.message || data.error || "Yêu cầu không thành công.");
    return data;
  }
  async function authenticate() {
    initData = tg?.initData || "";
    if (!initData) {
      setSession("Chưa xác thực", "Vui lòng mở Mini App từ bot Telegram để xác thực tài khoản.");
      return;
    }
    const preview = tg?.initDataUnsafe?.user;
    if (preview) {
      $("#greeting").textContent = "Xin chào " + (preview.first_name || "bạn") + " 👋";
      $("#avatar").textContent = (preview.first_name || "H").slice(0, 1).toUpperCase();
    }
    try {
      const auth = await post("/api/telegram/auth", { initData });
      authenticated = true;
      const user = auth.user || {};
      $("#greeting").textContent = "Xin chào " + (user.first_name || "bạn") + " 👋";
      $("#telegram-id").textContent = user.id ? String(user.id) : "Đã xác thực";
      setSession("Đã xác thực", "Tài khoản Telegram đã được máy chủ xác minh chữ ký.");
      try {
        const dashboard = await post("/api/dashboard", { initData });
        if (dashboard.profile?.id) $("#telegram-id").textContent = String(dashboard.profile.id);
        if (dashboard.stats?.balance !== null && dashboard.stats?.balance !== undefined) $("#stat-balance").textContent = String(dashboard.stats.balance);
        if (dashboard.stats?.orders !== null && dashboard.stats?.orders !== undefined) $("#stat-orders").textContent = String(dashboard.stats.orders);
        if (dashboard.stats?.tickets !== null && dashboard.stats?.tickets !== undefined) $("#stat-tickets").textContent = String(dashboard.stats.tickets);
      } catch (error) { console.warn("Dashboard chưa sẵn sàng:", error.message); }
    } catch (error) {
      authenticated = false;
      setSession("Không hợp lệ", "Xác thực thất bại: " + error.message);
    }
  }

  document.querySelectorAll("[data-service]").forEach((button) => {
    button.addEventListener("click", () => {
      const service = button.dataset.service;
      if (service === "deposit") {
        openPayment();
        return;
      }
      const message = serviceText[service] || "Dịch vụ đang cập nhật.";
      if (tg?.showAlert) tg.showAlert(message); else toast(message);
    });
  });

  async function openPayment() {
    if (!authenticated) {
      toast("Bạn cần mở Mini App từ Telegram và xác thực trước.");
      return;
    }
    $("#payment-status").textContent = "Đang tải thông tin nhận tiền…";
    $("#payment-modal").showModal();
    $("#payment-qr").hidden = true;
    try {
      const payment = await post("/api/payment/config", { initData });
      if (!payment.configured) {
        $("#payment-status").textContent = "Chưa cấu hình tài khoản nhận tiền. Vui lòng liên hệ CSKH.";
        return;
      }
      $("#payment-bank").textContent = payment.bankName || payment.bankCode || "—";
      $("#payment-account").textContent = payment.accountNumber || "—";
      $("#payment-owner").textContent = payment.accountName || "—";
      $("#payment-note").textContent = payment.transferNote || "—";
      if (payment.qrUrl && /^https:\/\//i.test(payment.qrUrl)) {
        $("#payment-qr").src = payment.qrUrl;
        $("#payment-qr").hidden = false;
      }
      $("#payment-status").textContent = "Thông tin nhận tiền đã được tải từ máy chủ.";
    } catch (error) {
      $("#payment-status").textContent = "Không tải được thông tin thanh toán: " + error.message;
    }
  }

  $("#open-ticket").addEventListener("click", () => $("#ticket-modal").showModal());
  $("#copy-account").addEventListener("click", async () => {
    const account = $("#payment-account").textContent;
    if (!account || account === "—") return;
    try {
      await navigator.clipboard.writeText(account);
      toast("Đã sao chép số tài khoản.");
    } catch {
      toast("Không thể sao chép tự động. Vui lòng chọn và sao chép số tài khoản.");
    }
  });
  $("#open-tool").addEventListener("click", () => $("#tool-modal").showModal());
  $("#ticket-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!authenticated) { toast("Bạn cần mở Mini App từ Telegram và xác thực trước."); return; }
    const subject = $("#ticket-subject").value.trim();
    const message = $("#ticket-message").value.trim();
    try {
      await post("/api/tickets", { initData, subject, message });
      toast("Đã gửi yêu cầu.");
      $("#ticket-modal").close();
    } catch (error) {
      toast(error.message);
    }
  });
  authenticate();
})();