import cors from "cors";
import express, { NextFunction, Request, Response } from "express";
import helmet from "helmet";
import parseRouter from "./routes/parse";

const app = express();
const PORT = process.env.AST_SERVICE_PORT ?? 3002;
const REQUEST_TIMEOUT_MS = 30_000;

const allowedOrigins = [
  process.env.BACKEND_URL ?? "http://localhost:8000",
  process.env.FRONTEND_URL ?? "http://localhost:5173",
];

app.use(
  helmet({
    // The AST service is an internal API, so we can use sensible defaults
    crossOriginResourcePolicy: { policy: "same-site" },
    contentSecurityPolicy: false,
  }),
);

app.use(
  cors({
    origin: (origin, callback) => {
      // Allow requests with no origin (server-to-server, health checks)
      if (!origin || allowedOrigins.includes(origin)) {
        callback(null, true);
      } else {
        callback(new Error(`CORS: origin ${origin} not allowed`));
      }
    },
    methods: ["GET", "POST"],
    allowedHeaders: ["Content-Type", "Authorization"],
  }),
);

app.use(express.json({ limit: "10mb" }));

// Enforce a 30-second timeout on all /api/ast requests
app.use("/api/ast", (_req: Request, res: Response, next: NextFunction) => {
  res.setTimeout(REQUEST_TIMEOUT_MS, () => {
    res
      .status(408)
      .json({ success: false, error: "Request timeout after 30 seconds" });
  });
  next();
});

app.get("/health", (_req, res) => {
  res.json({ status: "healthy", service: "ast-service" });
});

app.use("/api/ast", parseRouter);

// Only start the HTTP server when running directly (not when imported by tests)
if (require.main === module) {
  app.listen(PORT, () => {
    console.log(`AST service running on port ${PORT}`);
  });
}

export default app;
