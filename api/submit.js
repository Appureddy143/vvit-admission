import express from "express";
import { Pool } from "pg";
import formidable from "formidable";
import fs from "fs";
import PDFDocument from "pdfkit";

const app = express();
const pool = new Pool({ connectionString: process.env.DATABASE_URL });

// Helper to generate unique admission ID
function generateAdmissionID(branch) {
  const year = new Date().getFullYear().toString().slice(-2);
  const branchCode = branch.substring(0, 2).toUpperCase();
  const randomNum = Math.floor(Math.random() * 900 + 100);
  return `1VJ${year}${branchCode}${randomNum}`;
}

app.post("/api/submit", async (req, res) => {
  try {
    const form = formidable({ multiples: true, uploadDir: "/tmp", keepExtensions: true });

    form.parse(req, async (err, fields, files) => {
      if (err) return res.status(400).json({ error: "Form parse error" });

      const {
        student_name,
        father_name,
        email,
        mobile_number,
        branch,
        kea_number,
        caste,
      } = fields;

      const admission_id = generateAdmissionID(branch);

      // Insert into SQL database
      const client = await pool.connect();
      await client.query(
        `INSERT INTO admissions (admission_id, student_name, father_name, email, mobile_number, branch, kea_number, caste)
         VALUES ($1,$2,$3,$4,$5,$6,$7,$8)`,
        [admission_id, student_name, father_name, email, mobile_number, branch, kea_number, caste]
      );
      client.release();

      // Create PDF admission slip
      const pdfPath = `/tmp/${admission_id}.pdf`;
      const doc = new PDFDocument();
      doc.pipe(fs.createWriteStream(pdfPath));
      doc.fontSize(18).text("VVIT Admission Slip", { align: "center" });
      doc.moveDown();
      doc.fontSize(14).text(`Admission ID: ${admission_id}`);
      doc.text(`Name: ${student_name}`);
      doc.text(`Father Name: ${father_name}`);
      doc.text(`Branch: ${branch}`);
      doc.text(`KEA Number: ${kea_number}`);
      doc.text(`Mobile: ${mobile_number}`);
      doc.text(`Caste: ${caste}`);
      doc.end();

      // Auto WhatsApp message
      const whatsappMessage = `🎓 VVIT Admission Successful!\nAdmission ID: ${admission_id}\nName: ${student_name}\nBranch: ${branch}`;
      const encodedMsg = encodeURIComponent(whatsappMessage);
      const whatsappUrl = `https://wa.me/91${mobile_number}?text=${encodedMsg}`;

      res.json({
        success: true,
        message: "Documents uploaded successfully",
        admission_id,
        pdfUrl: `/uploads/${admission_id}.pdf`,
        whatsappUrl,
      });
    });
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: "Something went wrong. Please try again." });
  }
});

export default app;
