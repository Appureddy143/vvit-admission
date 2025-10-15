import { sql } from '@vercel/postgres';
import { put } from '@vercel/blob';
import { formidable } from 'formidable';
import fs from 'fs';
import fetch from 'node-fetch';
import { PDFDocument, rgb, StandardFonts } from 'pdf-lib';
import path from 'path';

export const config = { api: { bodyParser: false } };

export default async function handler(request, response) {
  if (request.method !== 'POST') {
    return response.status(405).json({ error: 'Method Not Allowed' });
  }

  try {
    const { fields, files } = await parseForm(request);
    const studentId = await generateStudentIdFromDb();
    const fileLinks = await uploadFilesToBlob(files, studentId);
    await saveDataToDb({ ...fields, ...fileLinks, student_id_text: studentId });
    
    // --- DEBUGGING STEP: PDF GENERATION IS TEMPORARILY DISABLED ---
    // const pdfBytes = await generatePdf({ ...fields, student_id: studentId }, fileLinks.photo_url);
    // const pdfBase64 = Buffer.from(pdfBytes).toString('base64');

    return response.status(200).json({
        message: 'Application submitted successfully!',
        studentId: studentId,
        pdfData: null // Send null since we are not generating a PDF
    });

  } catch (error) {
    console.error('--- API ERROR ---', error.message, error.stack);
    return response.status(500).json({ error: 'Internal Server Error', details: error.message });
  }
}

// --- Helper Functions ---

async function parseForm(request) {
    const form = formidable({});
    const [fields, files] = await form.parse(request);
    const singleValueFields = Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, value[0]]));
    return { fields: singleValueFields, files };
}

async function generateStudentIdFromDb() {
    const timeResponse = await fetch('http://worldtimeapi.org/api/timezone/Asia/Kolkata');
    if (!timeResponse.ok) throw new Error('Failed to fetch current time');
    const timeData = await timeResponse.json();
    const year = new Date(timeData.utc_datetime).getFullYear().toString().slice(-2);
    const prefix = `1VJ${year}`;

    const { rows } = await sql`
        SELECT student_id_text FROM students 
        WHERE student_id_text LIKE ${prefix + '%'} 
        ORDER BY id DESC 
        LIMIT 1;
    `;

    let newSerial = 1;
    if (rows.length > 0) {
        const lastId = rows[0].student_id_text;
        const lastSerial = parseInt(lastId.substring(prefix.length), 10);
        newSerial = lastSerial + 1;
    }
    return `${prefix}${String(newSerial).padStart(3, '0')}`;
}

async function uploadFilesToBlob(files, studentId) {
    const links = {};
    const uploadPromises = [];
    
    for (const key in files) {
        const file = files[key][0];
        if (file) {
            const fileExtension = path.extname(file.originalFilename);
            const newFilename = `${studentId}_${key}${fileExtension}`;
            
            const promise = put(newFilename, fs.createReadStream(file.filepath), {
                access: 'public',
            }).then(blob => {
                links[`${key}_url`] = blob.url;
            });
            uploadPromises.push(promise);
        }
    }
    await Promise.all(uploadPromises);
    return links;
}

async function saveDataToDb(data) {
    await sql`
        INSERT INTO students (
            student_id_text, student_name, dob, father_name, mother_name, 
            mobile_number, parent_mobile_number, email, permanent_address, 
            previous_college, previous_combination, category, sub_caste, 
            admission_through, cet_number, seat_allotted, allotted_branch_kea, 
            allotted_branch_management, cet_rank, photo_url, marks_card_url, 
            aadhaar_front_url, aadhaar_back_url, caste_income_url
        ) VALUES (
            ${data.student_id_text}, ${data.student_name}, ${data.dob}, ${data.father_name}, ${data.mother_name},
            ${data.mobile_number}, ${data.parent_mobile_number}, ${data.email}, ${data.permanent_address},
            ${data.previous_college}, ${data.previous_combination}, ${data.category}, ${data.sub_caste},
            ${data.admission_through}, ${data.cet_number}, ${data.seat_allotted}, ${data.allotted_branch_kea},
            ${data.allotted_branch_management}, ${data.cet_rank}, ${data.photo_url}, ${data.marks_card_url},
            ${data.aadhaar_front_url}, ${data.aadhaar_back_url}, ${data.caste_income_url}
        );
    `;
}

// This function is not being used in this test
async function generatePdf(data, photoUrl) {
    // ... PDF generation code is still here, just not being called
}

