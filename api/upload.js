import formidable from "formidable";
import fs from "fs";
import { Pool } from "pg";

export const config = {
  api: {
    bodyParser: false, // allow file upload
  },
};

// Neon database connection
const pool = new Pool({
  connectionString: process.env.DATABASE_URL, // set in Vercel env vars
  ssl: { rejectUnauthorized: false },
});

export default async function handler(req, res) {
  if (req.method !== "POST") {
    return res.status(405).json({ error: "Method not allowed" });
  }

  const form = formidable({ multiples: false, uploadDir: "/tmp", keepExtensions: true });

  form.parse(req, async (err, fields, files) => {
    if (err) {
      console.error(err);
      return res.status(500).json({ error: "Upload error" });
    }

    try {
      const name = fields.name?.[0];
      const email = fields.email?.[0];
      const branch = fields.branch?.[0];
      const year = new Date().getFullYear().toString().slice(2);
      const filePath = files.document?.[0]?.filepath;

      // Generate unique ID
      const uniqueId = `1VJ${year}${branch}${Math.floor(100 + Math.random() * 900)}`;

      // Save info in PostgreSQL
      const client = await pool.connect();
      await client.query(
        "INSERT INTO uploads (name, email, branch, year, file_path, unique_id) VALUES ($1,$2,$3,$4,$5,$6)",
        [name, email, branch, year, filePath, uniqueId]
      );
      client.release();

      // Optional: Send ID via WhatsApp API (using Twilio or other service)
      console.log("✅ Uploaded successfully:", uniqueId);

      res.status(200).json({ message: "Uploaded successfully", id: uniqueId });
    } catch (error) {
      console.error(error);
      res.status(500).json({ error: "Something went wrong" });
    }
  });
}
