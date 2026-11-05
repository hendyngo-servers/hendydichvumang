export const BOT_MENU_KEYS = Object.freeze(["grammy", "aiogram"]);

export const MENU_ACTIONS = Object.freeze({
  grammy: ["customer", "balance", "services", "support"],
  aiogram: ["customer", "balance", "services", "support", "admin", "automation"]
});

const DEFAULT_LABELS = Object.freeze({
  customer: "Trung tâm khách hàng",
  balance: "Số dư",
  services: "Dịch vụ",
  support: "Hỗ trợ",
  admin: "Quản trị",
  automation: "Tự động hóa"
});

const DEFAULT_ICONS = Object.freeze({
  customer: "👤",
  balance: "💳",
  services: "🛍",
  support: "🎫",
  admin: "🛠",
  automation: "⚙️"
});

const DEFAULT_ORDER = Object.freeze({
  grammy: ["customer", "balance", "services", "support"],
  aiogram: ["customer", "balance", "services", "support", "admin", "automation"]
});

function isRecord(value) {
  return value !== null && typeof value === "object" && !Array.isArray(value);
}

function cleanMap(value, allowed, maximum) {
  const result = {};
  if (!isRecord(value)) return result;
  for (const key of allowed) {
    if (typeof value[key] !== "string") continue;
    const item = value[key].trim();
    if (item && [...item].length <= maximum) result[key] = item;
  }
  return result;
}

export function defaultMenuConfig(botKey) {
  if (!BOT_MENU_KEYS.includes(botKey)) throw new TypeError("Bot key không hợp lệ.");
  return {
    botKey,
    introText: botKey === "aiogram"
      ? "Chọn chức năng HENDY. Quản trị và tự động hóa chỉ hiển thị với tài khoản được cấp quyền."
      : "Chọn chức năng bạn cần bên dưới.",
    menuColumns: 2,
    persistentKeyboard: true,
    resizeKeyboard: true,
    buttonOrder: [...DEFAULT_ORDER[botKey]],
    buttonLabels: {},
    buttonIcons: Object.fromEntries(MENU_ACTIONS[botKey].map((key) => [key, DEFAULT_ICONS[key]])),
    updatedAt: null
  };
}

export function normalizeMenuConfig(input, botKey) {
  const fallback = defaultMenuConfig(botKey);
  const source = isRecord(input) ? input : {};
  const rawColumns = Number(source.menuColumns ?? source.menu_columns ?? fallback.menuColumns);
  const menuColumns = Number.isInteger(rawColumns) && rawColumns >= 1 && rawColumns <= 3
    ? rawColumns
    : fallback.menuColumns;
  const allowed = MENU_ACTIONS[botKey];
  const rawOrder = source.buttonOrder ?? source.button_order;
  const buttonOrder = Array.isArray(rawOrder)
    ? [...new Set(rawOrder.filter((item) => typeof item === "string" && allowed.includes(item)))]
    : [...fallback.buttonOrder];
  const safeOrder = buttonOrder.includes("customer")
    ? buttonOrder
    : ["customer", ...buttonOrder].filter((key, index, all) => all.indexOf(key) === index);

  const labels = cleanMap(source.buttonLabels ?? source.button_labels, allowed, 40);
  const icons = cleanMap(source.buttonIcons ?? source.button_icons, allowed, 8);
  const introRaw = source.introText ?? source.intro_text ?? fallback.introText;

  return {
    botKey,
    introText: typeof introRaw === "string" ? [...introRaw.trim()].slice(0, 500).join("") : fallback.introText,
    menuColumns,
    persistentKeyboard: typeof (source.persistentKeyboard ?? source.persistent_keyboard) === "boolean"
      ? (source.persistentKeyboard ?? source.persistent_keyboard)
      : fallback.persistentKeyboard,
    resizeKeyboard: typeof (source.resizeKeyboard ?? source.resize_keyboard) === "boolean"
      ? (source.resizeKeyboard ?? source.resize_keyboard)
      : fallback.resizeKeyboard,
    buttonOrder: safeOrder.length ? safeOrder : [...fallback.buttonOrder],
    buttonLabels: labels,
    buttonIcons: { ...fallback.buttonIcons, ...icons },
    updatedAt: source.updatedAt ?? source.updated_at ?? null
  };
}

export function validateMenuConfigInput(input, botKey) {
  if (!BOT_MENU_KEYS.includes(botKey)) throw new TypeError("Bot key không hợp lệ.");
  if (!isRecord(input)) throw new TypeError("Dữ liệu cấu hình menu không hợp lệ.");
  const allowed = MENU_ACTIONS[botKey];
  const columns = Number(input.menuColumns);
  if (!Number.isInteger(columns) || columns < 1 || columns > 3) {
    throw new TypeError("Số cột menu phải từ 1 đến 3.");
  }
  if (typeof input.persistentKeyboard !== "boolean" || typeof input.resizeKeyboard !== "boolean") {
    throw new TypeError("Tùy chọn bàn phím phải là true hoặc false.");
  }
  if (typeof input.introText !== "string" || [...input.introText].length > 500) {
    throw new TypeError("Lời giới thiệu menu tối đa 500 ký tự.");
  }
  if (!Array.isArray(input.buttonOrder) || input.buttonOrder.length < 1 ||
      input.buttonOrder.length > allowed.length ||
      input.buttonOrder.some((key) => typeof key !== "string" || !allowed.includes(key)) ||
      new Set(input.buttonOrder).size !== input.buttonOrder.length ||
      !input.buttonOrder.includes("customer")) {
    throw new TypeError("Thứ tự nút không hợp lệ; phải giữ nút Trung tâm khách hàng.");
  }
  for (const field of ["buttonLabels", "buttonIcons"]) {
    const values = input[field];
    if (!isRecord(values)) throw new TypeError("Nhãn và biểu tượng nút phải là đối tượng.");
    for (const [key, value] of Object.entries(values)) {
      if (!allowed.includes(key) || typeof value !== "string" ||
          [...value.trim()].length > (field === "buttonLabels" ? 40 : 8)) {
        throw new TypeError("Nhãn hoặc biểu tượng nút không hợp lệ.");
      }
    }
  }
  return normalizeMenuConfig(input, botKey);
}

export function menuButtonText(config, action) {
  const label = config.buttonLabels[action] || DEFAULT_LABELS[action] || action;
  const icon = config.buttonIcons[action] || "";
  return [icon, label].filter(Boolean).join(" ").trim();
}

export function visibleMenuActions(config, isAdmin = false) {
  return config.buttonOrder.filter((action) => {
    if (action === "admin" || action === "automation") return isAdmin && config.botKey === "aiogram";
    return MENU_ACTIONS[config.botKey].includes(action);
  });
}

export function buildMenuRows(config, isAdmin = false) {
  const actions = visibleMenuActions(config, isAdmin);
  const rows = [];
  for (let index = 0; index < actions.length; index += config.menuColumns) {
    rows.push(actions.slice(index, index + config.menuColumns).map((action) => ({
      action,
      text: menuButtonText(config, action)
    })));
  }
  return rows;
}

export function resolveMenuAction(text, config, isAdmin = false) {
  const value = String(text ?? "").trim();
  for (const action of visibleMenuActions(config, isAdmin)) {
    if (menuButtonText(config, action) === value) return action;
  }
  const legacy = {
    customer: ["👤 Trung tâm khách hàng", "👤 Tài khoản"],
    balance: ["💳 Số dư"],
    services: ["🛍 Dịch vụ"],
    support: ["🎫 Hỗ trợ"],
    admin: ["🛠 Quản trị"],
    automation: ["⚙️ Tự động hóa"]
  };
  for (const action of visibleMenuActions(config, isAdmin)) {
    if ((legacy[action] || []).includes(value)) return action;
  }
  return null;
}
