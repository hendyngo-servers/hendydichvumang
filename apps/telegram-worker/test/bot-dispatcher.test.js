import test from "node:test";
import assert from "node:assert/strict";
import { BotDispatcher } from "../src/core/bot-dispatcher.js";

function context(overrides = {}) {
  return {
    ensureUser: async () => {},
    getSession: async () => null,
    answerCallbackQuery: async () => {},
    sendMessage: async (...args) => args,
    editOrSend: async (value) => value,
    mainKeyboard: () => ({ inline_keyboard: [] }),
    isAdmin: () => false,
    ...overrides
  };
}

test("dispatcher resolves Telegram commands with optional bot mentions", async () => {
  const dispatcher = new BotDispatcher();
  let received;
  dispatcher.command("start", (ctx) => { received = ctx; });
  await dispatcher.dispatch({ message: {
    chat: { id: 10 }, from: { id: 20 }, text: "/start@HendyBot_Bot argument"
  } }, {}, {}, context());
  assert.equal(received.telegramUserId, 20);
  assert.equal(received.args, "argument");
});

test("active conversation session receives non-command messages", async () => {
  const dispatcher = new BotDispatcher();
  let received;
  dispatcher.session("support:subject", (ctx) => { received = ctx.text; });
  await dispatcher.dispatch({ message: {
    chat: { id: 10 }, from: { id: 20 }, text: "Không đăng nhập được"
  } }, {}, {}, context({ getSession: async () => ({ action: "support:subject" }) }));
  assert.equal(received, "Không đăng nhập được");
});

test("registered commands take precedence over an active session", async () => {
  const dispatcher = new BotDispatcher();
  let selected = "";
  dispatcher.command("cancel", () => { selected = "command"; });
  dispatcher.session("support:subject", () => { selected = "session"; });
  await dispatcher.dispatch({ message: {
    chat: { id: 10 }, from: { id: 20 }, text: "/cancel"
  } }, {}, {}, context({ getSession: async () => ({ action: "support:subject" }) }));
  assert.equal(selected, "command");
});

test("callback prefix handler routes data without logging callback payload content", async () => {
  const dispatcher = new BotDispatcher();
  let received;
  dispatcher.callback("service:*", (ctx) => { received = ctx.data; });
  await dispatcher.dispatch({ callback_query: {
    id: "cb-1", data: "service:proxy", from: { id: 20 },
    message: { message_id: 3, chat: { id: 10 } }
  } }, {}, {}, context());
  assert.equal(received, "service:proxy");
});

test("global regular-expression callback patterns are repeatable", async () => {
  const dispatcher = new BotDispatcher();
  let calls = 0;
  dispatcher.callback(/^service:(?:otp|proxy)$/g, () => { calls += 1; });
  const helpers = context();
  for (const id of [1, 2]) {
    await dispatcher.dispatch({ callback_query: {
      id: String(id), data: "service:proxy", from: { id: 20 },
      message: { message_id: id, chat: { id: 10 } }
    } }, {}, {}, helpers);
  }
  assert.equal(calls, 2);
});

test("admin-only commands are rejected before invoking the handler", async () => {
  const dispatcher = new BotDispatcher();
  let invoked = false;
  dispatcher.command("ops", () => { invoked = true; }, { adminOnly: true });
  let denied = "";
  await dispatcher.dispatch({ message: {
    chat: { id: 10 }, from: { id: 20 }, text: "/ops"
  } }, {}, {}, context({ sendMessage: async (_chat, message) => { denied = message; } }));
  assert.equal(invoked, false);
  assert.match(denied, /không có quyền/i);
});
