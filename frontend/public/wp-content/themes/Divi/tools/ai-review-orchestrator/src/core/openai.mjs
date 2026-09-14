import OpenAI from "openai";

import { recordOpenAIUsage } from "./usage.mjs";

let currentSubtaskName = "openai";

export const setOpenAIUsageSubtask = (subtaskName) => {
  currentSubtaskName =
    "string" === typeof subtaskName && "" !== subtaskName.trim()
      ? subtaskName.trim()
      : "openai";
};

export const getOpenAIClient = () => {
  const key = process.env.OPENAI_API_KEY;
  if ("string" !== typeof key || "" === key.trim()) {
    return null;
  }
  const client = new OpenAI({ apiKey: key });
  const originalCreate = client.responses.create.bind(client.responses);
  client.responses.create = async (...args) => {
    const startedAt = Date.now();
    const fallbackModel =
      "string" === typeof args[0]?.model ? args[0].model : undefined;
    try {
      const response = await originalCreate(...args);
      recordOpenAIUsage({
        response,
        fallbackModel,
        durationMs: Date.now() - startedAt,
        subtaskName: currentSubtaskName,
        status: "success",
      });
      return response;
    } catch (error) {
      recordOpenAIUsage({
        response: error?.error || error?.response,
        fallbackModel,
        durationMs: Date.now() - startedAt,
        subtaskName: currentSubtaskName,
        status: "error",
      });
      throw error;
    }
  };
  return client;
};
