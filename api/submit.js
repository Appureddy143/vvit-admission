import { google } from 'googleapis';
import { formidable } from 'formidable';
import fs from 'fs';
import fetch from 'node-fetch';

// Disable Vercel's default body parsing to handle file uploads
export const config = {
  api: {
    bodyParser: false,
  },
};

// --- Main Handler ---
export default async function handler(request, response) {
  if (request.method !== 'POST') {
    return response.status(405).json({ error: 'Method Not Allowed' });
  }

  try {
    console.log("API execution started.");

    // --- Authenticate with Google ---
    console.log("Step 1: Authenticating with Google...");
    const auth = new google.auth.GoogleAuth({
        credentials: {
            client_email: process.env.GOOGLE_CLIENT_EMAIL,
            private_key: process.env.GOOGLE_PRIVATE_KEY.replace(/\\n/g, '\n'),
        },
        scopes: [
            'https://www.googleapis.com/auth/spreadsheets',
            'https://www.googleapis.com/auth/drive',
        ],
    });
    const sheets = google.sheets({ version: 'v4', auth });
    const drive = google.drive({ version: 'v3', auth });
    console.log("Authentication successful.");

    console.log("Step 2: Parsing form data...");
    const { fields, files } = await parseForm(request);
    console.log("Form parsing successful. Student name:", fields.student_name);
    
    console.log("Step 3: Generating Student ID...");
    const studentId = await generateStudentIdFromSheet(sheets);
    console.log("Student ID generated:", studentId);

    console.log("Step 4: Creating Google Drive folder...");
    const studentFolderId = await createStudentFolder(drive, studentId, fields.student_name);
    console.log("Drive folder created with ID:", studentFolderId);

    console.log("Step 5: Uploading files to Google Drive...");
    const fileLinks = await uploadFilesToDrive(drive, files, studentFolderId);
    console.log("File uploads successful. Links:", fileLinks);
    
    console.log("Step 6: Saving data to Google Sheet...");
    await saveDataToSheet(sheets, { ...fields, ...fileLinks, student_id: studentId });
    console.log("Data saved to sheet successfully.");

    return response.status(200).json({
        message: 'Application submitted successfully!',
        studentId: studentId
    });

  } catch (error) {
    // This will log the detailed error to your Vercel logs
    console.error('--- API ERROR ---');
    console.error('Error Message:', error.message);
    console.error('Full Error Object:', error);
    console.error('--- END OF ERROR ---');
    return response.status(500).json({ error: 'Internal Server Error' });
  }
}

// --- Helper Functions ---
// (The helper functions below are the same as before, no changes needed there)

async function parseForm(request) {
    const form = formidable({});
    const [fields, files] = await form.parse(request);
    const singleValueFields = Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, value[0]]));
    return { fields: singleValueFields, files };
}

async function generateStudentIdFromSheet(sheets) {
    const timeResponse = await fetch('http://worldtimeapi.org/api/timezone/Asia/Kolkata');
    if (!timeResponse.ok) throw new Error('Failed to fetch current time');
    const timeData = await timeResponse.json();
    const year = new Date(timeData.utc_datetime).getFullYear().toString().slice(-2);
    const prefix = `1VJ${year}`;

    const res = await sheets.spreadsheets.values.get({
        spreadsheetId: process.env.GOOGLE_SHEET_ID,
        range: 'Sheet1!A:A',
    });

    const lastId = res.data.values ? res.data.values[res.data.values.length - 1][0] : null;
    let newSerial = 1;
    if (lastId && lastId.startsWith(prefix)) {
        newSerial = parseInt(lastId.substring(prefix.length)) + 1;
    }
    return `${prefix}${String(newSerial).padStart(3, '0')}`;
}

async function createStudentFolder(drive, studentId, studentName) {
    const folderMetadata = {
        name: `${studentId} - ${studentName}`,
        parents: [process.env.GOOGLE_DRIVE_FOLDER_ID],
        mimeType: 'application/vnd.google-apps.folder',
    };
    const folder = await drive.files.create({
        resource: folderMetadata,
        fields: 'id',
    });
    return folder.data.id;
}

async function uploadFilesToDrive(drive, files, parentFolderId) {
    const links = {};
    for (const key in files) {
        const file = files[key][0];
        if (file) {
            const fileMetadata = { name: file.originalFilename, parents: [parentFolderId] };
            const media = { mimeType: file.mimetype, body: fs.createReadStream(file.filepath) };
            
            const driveFile = await drive.files.create({
                resource: fileMetadata,
                media: media,
                fields: 'id, webViewLink',
            });
            links[`${key}_url`] = driveFile.data.webViewLink;
        }
    }
    return links;
}

async function saveDataToSheet(sheets, data) {
    const headers = [
        'student_id', 'student_name', 'dob', 'father_name', 'mother_name', 
        'mobile_number', 'parent_mobile_number', 'email', 'previous_college', 
        'previous_combination', 'permanent_address', 'category', 'sub_caste', 
        'admission_through', 'cet_number', 'seat_allotted', 'allotted_branch_kea', 'allotted_branch_management',
        'cet_rank', 'photo_url', 'marks_card_url', 'aadhaar_front_url', 
        'aadhaar_back_url', 'caste_income_url', 'submission_date'
    ];

    const row = headers.map(header => data[header] || '');
    row[headers.indexOf('submission_date')] = new Date().toISOString();

    await sheets.spreadsheets.values.append({
        spreadsheetId: process.env.GOOGLE_SHEET_ID,
        range: 'Sheet1',
        valueInputOption: 'USER_ENTERED',
        resource: { values: [row] },
    });
}
