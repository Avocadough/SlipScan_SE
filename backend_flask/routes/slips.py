"""
backend_flask/routes/slips.py
Endpoints: upload, upload-batch, get by id, list all
"""
import os
import uuid
import json
import hashlib
import requests
from pathlib import Path
from flask import Blueprint, request, jsonify, send_from_directory
from config import get_db
from auth_guard import require_auth

slips_bp = Blueprint("slips", __name__, url_prefix="/api/slips")

UPLOAD_DIR    = Path(__file__).parent.parent / "uploads"
UPLOAD_DIR.mkdir(exist_ok=True)
ALLOWED_EXT   = {".jpg", ".jpeg", ".png", ".webp"}
MAX_SIZE_BYTES = 10 * 1024 * 1024  # 10 MB
MAX_BATCH      = 20
OCR_URL        = os.environ.get("OCR_SERVICE_URL", "http://localhost:5000/ocr")


# ── GET /api/slips/uploads/<filename>  (serve uploaded images) ───────────────
@slips_bp.get("/uploads/<path:filename>")
def serve_upload(filename):
    return send_from_directory(str(UPLOAD_DIR), filename)


# ── POST /api/slips/upload ───────────────────────────────────────────────────
@slips_bp.post("/upload")
@require_auth
def upload():
    user_id = int(request.current_user["sub"])

    if "file" not in request.files:
        return jsonify({"success": False, "message": "No file uploaded (field: 'file')"}), 400

    file  = request.files["file"]
    error = _validate_file(file)
    if error:
        return jsonify({"success": False, "message": error}), 400

    filename = f"slip_{uuid.uuid4().hex}{Path(file.filename).suffix.lower()}"
    dest     = UPLOAD_DIR / filename
    file.save(str(dest))

    ocr_data, warnings = _call_ocr(str(dest))
    if ocr_data is None:
        warnings.append("OCR service unavailable or failed")
        ocr_data = {}

    slip_id = _save_slip(user_id, f"uploads/{filename}", ocr_data)

    return jsonify({
        "success":  True,
        "slip_id":  slip_id,
        "data": {
            "sender_name":   ocr_data.get("sender_name"),
            "bank_name":     ocr_data.get("bank_name"),
            "amount":        ocr_data.get("amount"),
            "slip_date":     ocr_data.get("slip_date"),
            "slip_time":     ocr_data.get("slip_time"),
            "ref_no":        ocr_data.get("ref_no"),
            "receiver_name": ocr_data.get("receiver_name"),
            "receiver_acct": ocr_data.get("receiver_account"),
        },
        "raw_ocr":  ocr_data.get("raw_ocr"),
        "warnings": warnings,
    }), 200


# ── POST /api/slips/upload-batch ─────────────────────────────────────────────
@slips_bp.post("/upload-batch")
@require_auth
def upload_batch():
    user_id = int(request.current_user["sub"])
    files   = request.files.getlist("files")

    if not files:
        return jsonify({"success": False, "message": "No files uploaded (field: 'files[]')"}), 400

    if len(files) > MAX_BATCH:
        return jsonify({"success": False, "message": f"Maximum {MAX_BATCH} files per batch"}), 400

    results      = []
    success_count = 0
    failed_count  = 0

    for i, file in enumerate(files):
        error = _validate_file(file)
        if error:
            results.append({"index": i, "success": False, "error": error})
            failed_count += 1
            continue

        filename = f"slip_{uuid.uuid4().hex}{Path(file.filename).suffix.lower()}"
        dest     = UPLOAD_DIR / filename
        file.save(str(dest))

        ocr_data, warnings = _call_ocr(str(dest))
        if ocr_data is None:
            warnings.append("OCR service unavailable")
            ocr_data = {}

        slip_id = _save_slip(user_id, f"uploads/{filename}", ocr_data)
        results.append({
            "index":    i,
            "success":  True,
            "slip_id":  slip_id,
            "filename": file.filename,
            "data": {
                "sender_name":   ocr_data.get("sender_name"),
                "bank_name":     ocr_data.get("bank_name"),
                "amount":        ocr_data.get("amount"),
                "slip_date":     ocr_data.get("slip_date"),
                "slip_time":     ocr_data.get("slip_time"),
                "ref_no":        ocr_data.get("ref_no"),
                "receiver_name": ocr_data.get("receiver_name"),
                "receiver_acct": ocr_data.get("receiver_account"),
            },
            "warnings": warnings,
        })
        success_count += 1

    return jsonify({
        "success":       True,
        "total":         len(files),
        "success_count": success_count,
        "failed_count":  failed_count,
        "items":         results,
    }), 200


# ── GET /api/slips/<id> ──────────────────────────────────────────────────────
@slips_bp.get("/<int:slip_id>")
@require_auth
def get_by_id(slip_id):
    user_id = int(request.current_user["sub"])
    conn    = get_db()

    with conn.cursor() as cur:
        cur.execute("SELECT * FROM slips WHERE id = %s AND user_id = %s", (slip_id, user_id))
        slip = cur.fetchone()

    if not slip:
        return jsonify({"success": False, "message": "Slip not found"}), 404

    slip = dict(slip)
    if slip.get("created_at"):
        slip["created_at"] = str(slip["created_at"])

    return jsonify({"success": True, "data": slip}), 200


# ── GET /api/slips ───────────────────────────────────────────────────────────
@slips_bp.get("/")
@require_auth
def list_all():
    user_id  = int(request.current_user["sub"])
    page     = max(1, int(request.args.get("page", 1)))
    per_page = min(50, max(1, int(request.args.get("per_page", 20))))
    offset   = (page - 1) * per_page

    conn = get_db()
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS cnt FROM slips WHERE user_id = %s", (user_id,))
        total = cur.fetchone()["cnt"]

        cur.execute(
            """SELECT id, image_path, sender_name, bank_name, amount, slip_date, slip_time,
                      ref_no, receiver_name, receiver_acct, is_fake, is_duplicate, created_at
               FROM slips WHERE user_id = %s
               ORDER BY created_at DESC
               LIMIT %s OFFSET %s""",
            (user_id, per_page, offset),
        )
        slips = [dict(r) for r in cur.fetchall()]

    for s in slips:
        if s.get("created_at"):
            s["created_at"] = str(s["created_at"])

    return jsonify({
        "success":  True,
        "total":    total,
        "page":     page,
        "per_page": per_page,
        "data":     slips,
    }), 200


# ── Helpers ──────────────────────────────────────────────────────────────────

def _validate_file(file) -> str | None:
    if not file or not file.filename:
        return "Empty file"
    ext = Path(file.filename).suffix.lower()
    if ext not in ALLOWED_EXT:
        return f"Unsupported file type '{ext}'. Allowed: jpg, png, webp"
    file.seek(0, 2)
    size = file.tell()
    file.seek(0)
    if size > MAX_SIZE_BYTES:
        return "File too large (max 10MB)"
    return None


def _call_ocr(file_path: str) -> tuple[dict | None, list[str]]:
    warnings = []
    try:
        with open(file_path, "rb") as f:
            resp = requests.post(OCR_URL, files={"file": f}, timeout=60)
        if resp.status_code != 200:
            warnings.append(f"OCR service returned HTTP {resp.status_code}")
            return None, warnings
        body = resp.json()
        if not body.get("success"):
            warnings.append(body.get("error", "OCR failed"))
            return None, warnings
        warnings.extend(body.get("warnings", []))
        return body["data"], warnings
    except Exception as e:
        warnings.append(f"OCR service error: {e}")
        return None, warnings


def _save_slip(user_id: int, image_path: str, data: dict) -> int:
    conn = get_db()
    raw_ocr = json.dumps(data.get("raw_ocr"), ensure_ascii=False) if data.get("raw_ocr") else None

    with conn.cursor() as cur:
        cur.execute(
            """INSERT INTO slips
               (user_id, image_path, sender_name, bank_name, amount,
                slip_date, slip_time, ref_no, receiver_name, receiver_acct, raw_ocr)
               VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
               RETURNING id""",
            (
                user_id,
                image_path,
                data.get("sender_name"),
                data.get("bank_name"),
                data.get("amount"),
                data.get("slip_date") or None,
                data.get("slip_time") or None,
                data.get("ref_no"),
                data.get("receiver_name"),
                data.get("receiver_account"),
                raw_ocr,
            ),
        )
        slip_id = cur.fetchone()["id"]
        conn.commit()
    return slip_id
