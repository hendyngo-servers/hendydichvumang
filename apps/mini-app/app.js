(() => {
  const telegram = window.Telegram?.WebApp;
  const config = window.HENDY_CONFIG || {};
  const $ = (selector) => document.querySelector(selector);

  if (telegram) {
    telegram.ready();
    telegram.expand();
    telegram.setHeaderColor?.("#0b1020");
    telegram.setBackgroundColor?.("#0b1020");
  }

  $("#year").textContent = String(new Date().getFullYear());

  async function authenticate() {
    const initData = telegram?.initData || "";
    if (!initData) {
      $("#auth-status").textContent = "Hãy mở Mini App từ bot Telegram để xác thực tài khoản.";
      $("#session-state").textContent = "Chưa xác thực";
      return;
    }
    const previewUser = telegram?.initDataUnsafe?.user;
    if (previewUser?.first_name) $("#greeting").textContent = `Xin chào ${previewUser.first_name} 👋`;

    const apiBase = String(config.API_BASE_URL || "").trim().replace(/\/$/, "");
    if (!apiBase || apiBase.includes("__WORKER_API_URL__")) {
      $("#auth-status").textContent = "Mini App đã mở trong Telegram. Quản trị viên cần cấu hình WORKER_API_URL để bật xác thực máy chủ.";
      $("#session-state").textContent = "Chờ cấu hình API";
      return;
    }

    try {
      const response = await fetch(`${apiBase}/api/telegram/auth`, {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ initData }),
      });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || "Không thể xác thực phiên.");
      $("#greeting").textContent = `Xin chào ${result.user?.first_name || "bạn"} 👋`;
      $("#auth-status").textContent = "Tài khoản Telegram đã được máy chủ xác thực.";
      $("#session-state").textContent = "Đã xác thực";
    } catch (error) {
      $("#auth-status").textContent = `Xác thực chưa thành công: ${error.message}`;
      $("#session-state").textContent = "Không hợp lệ";
    }
  }

  const serviceMessages = {
    minigame: "Minigame Live hiện chưa kết nối hệ thống dịch vụ.",
    rental: "Danh mục thuê Tool đang chờ cấu hình sản phẩm.",
    shop: "Danh mục Proxy và sản phẩm đang chờ kết nối đơn hàng.",
    deposit: "Cổng nạp tiền chưa được cấu hình. Vui lòng liên hệ CSKH.",
    tools: "Kho công cụ đang được tích hợp từng phần.",
    support: "Vui lòng mở bot và chọn mục Giới thiệu & Hỗ trợ để liên hệ quản trị viên.",
  };
  document.querySelectorAll("[data-service]").forEach((button) => {
    button.addEventListener("click", () => {
      const message = serviceMessages[button.getAttribute("data-service")] || "Chức năng đang được cập nhật.";
      if (telegram?.showAlert) telegram.showAlert(message);
      else window.alert(message);
    });
  });
  authenticate();
})();
