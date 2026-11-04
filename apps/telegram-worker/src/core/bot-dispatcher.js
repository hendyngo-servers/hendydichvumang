import { safeLog } from "./safe-logger.js";

/**
 * Registry for Telegram commands, callbacks and short-lived conversation states.
 * Handlers are deliberately injected so the dispatcher is independent of D1 and
 * Telegram transport details.
 */
export class BotDispatcher {
  constructor() {
    this.commands = new Map();
    this.callbackPatterns = [];
    this.sessionHandlers = new Map();
    this.defaultHandler = null;
  }

  command(name, handler, options = {}) {
    const normalized = String(name || "").replace(/^\//, "").toLowerCase();
    if (!/^[a-z0-9_]{1,32}$/.test(normalized) || typeof handler !== "function") {
      throw new TypeError("Invalid Telegram command registration");
    }
    this.commands.set(normalized, {
      name: normalized,
      handler,
      adminOnly: Boolean(options.adminOnly)
    });
    return this;
  }

  callback(pattern, handler, options = {}) {
    if (typeof handler !== "function") throw new TypeError("Callback handler must be a function");
    let matcher;
    if (pattern instanceof RegExp) {
      const regex = new RegExp(pattern.source, pattern.flags.replace(/[gy]/g, ""));
      matcher = (value) => {
        regex.lastIndex = 0;
        return regex.test(value);
      };
    } else if (typeof pattern === "string" && pattern.endsWith("*")) {
      const prefix = pattern.slice(0, -1);
      matcher = (value) => value.startsWith(prefix);
    } else if (typeof pattern === "string") {
      matcher = (value) => value === pattern;
    } else {
      throw new TypeError("Callback pattern must be a string or RegExp");
    }
    this.callbackPatterns.push({ pattern, matcher, handler, adminOnly: Boolean(options.adminOnly) });
    return this;
  }

  session(action, handler) {
    if (typeof action !== "string" || !action || typeof handler !== "function") {
      throw new TypeError("Invalid conversation session registration");
    }
    this.sessionHandlers.set(action, handler);
    return this;
  }

  fallback(handler) {
    if (handler !== null && typeof handler !== "function") {
      throw new TypeError("Fallback handler must be a function or null");
    }
    this.defaultHandler = handler;
    return this;
  }

  async dispatch(update, env, db, helpers) {
    if (update?.message) return this.dispatchMessage(update.message, env, db, helpers);
    if (update?.callback_query) return this.dispatchCallback(update.callback_query, env, db, helpers);
  }

  async dispatchMessage(message, env, db, helpers) {
    const chatId = message?.chat?.id;
    const telegramUserId = Number(message?.from?.id);
    if (!chatId || !Number.isSafeInteger(telegramUserId) || telegramUserId <= 0) return;

    await helpers.ensureUser(db, message.from);
    const text = typeof message.text === "string" ? message.text.trim() : "";
    if (!text) return;

    const userIsAdmin = Boolean(helpers.isAdmin?.(env, telegramUserId));
    if (text.startsWith("/")) {
      const [rawCommand, ...args] = text.split(/\s+/);
      const commandName = rawCommand.slice(1).split("@")[0].toLowerCase();
      const registered = this.commands.get(commandName);
      if (registered) {
        if (registered.adminOnly && !userIsAdmin) {
          return helpers.sendMessage(chatId, "⛔ Bạn không có quyền truy cập chức năng này.");
        }
        safeLog("info", "bot.command", { telegramUserId, route: commandName });
        return registered.handler({
          message, chatId, telegramUserId, args: args.join(" "), text,
          env, db, userIsAdmin, helpers
        });
      }
    }

    const session = await helpers.getSession(db, telegramUserId);
    if (session?.action && this.sessionHandlers.has(session.action)) {
      return this.sessionHandlers.get(session.action)({
        message, chatId, telegramUserId, session, text,
        env, db, userIsAdmin, helpers
      });
    }

    if (this.defaultHandler) {
      return this.defaultHandler({
        message, chatId, telegramUserId, text, env, db, userIsAdmin, helpers
      });
    }
  }

  async dispatchCallback(query, env, db, helpers) {
    if (!query?.id) return;
    await helpers.answerCallbackQuery(query).catch(() => {});

    const message = query.message || null;
    const chatId = message?.chat?.id || query.from?.id;
    const telegramUserId = Number(query.from?.id);
    const messageId = message?.message_id || null;
    if (!chatId || !Number.isSafeInteger(telegramUserId) || telegramUserId <= 0) return;

    await helpers.ensureUser(db, query.from);
    const data = typeof query.data === "string" ? query.data.slice(0, 128) : "";
    const userIsAdmin = Boolean(helpers.isAdmin?.(env, telegramUserId));

    for (const entry of this.callbackPatterns) {
      if (!entry.matcher(data)) continue;
      if (entry.adminOnly && !userIsAdmin) {
        return helpers.editOrSend({
          chatId, messageId, env, text: "⛔ Bạn không có quyền thực hiện hành động này.",
          keyboard: helpers.mainKeyboard(env)
        });
      }
      safeLog("info", "bot.callback", { telegramUserId, route: data.slice(0, 80) });
      return entry.handler({
        query, message, messageId, chatId, telegramUserId, data,
        env, db, userIsAdmin, helpers
      });
    }

    return helpers.editOrSend({
      chatId, messageId, env,
      text: "🤖 Nút bấm hoặc phiên làm việc đã cũ. Vui lòng chọn lại chức năng.",
      keyboard: helpers.mainKeyboard(env)
    });
  }
}
