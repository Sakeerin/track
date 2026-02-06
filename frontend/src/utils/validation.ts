import { ValidationError } from '../types/tracking.types';

// Thai Post tracking number patterns
const THAI_POST_PATTERNS = [
  /^[A-Z]{2}\d{9}TH$/,  // Standard format: AB123456789TH
  /^[A-Z]{2}\d{9}$/,    // Without TH suffix: AB123456789
  /^TH\d{10}$/,         // TH prefix: TH1234567890
];

// Common courier patterns
const COURIER_PATTERNS = [
  /^[A-Z]{2}\d{8,12}$/,     // General format: AB12345678
  /^\d{10,15}$/,            // Numeric only: 1234567890
  /^[A-Z]\d{8,12}$/,        // Single letter + numbers: A12345678
  /^[A-Z]{3}\d{8,10}$/,     // Three letters + numbers: ABC12345678
];

/**
 * Validate a single tracking number format
 */
export function validateTrackingNumber(trackingNumber: string): boolean {
  if (!trackingNumber || typeof trackingNumber !== 'string') {
    return false;
  }

  const cleaned = trackingNumber.trim().toUpperCase();
  
  if (cleaned.length < 8 || cleaned.length > 20) {
    return false;
  }

  // Check against Thai Post patterns first
  for (const pattern of THAI_POST_PATTERNS) {
    if (pattern.test(cleaned)) {
      return true;
    }
  }

  // Check against general courier patterns
  for (const pattern of COURIER_PATTERNS) {
    if (pattern.test(cleaned)) {
      return true;
    }
  }

  return false;
}

/**
 * Parse and validate multiple tracking numbers from input text
 */
export function parseTrackingNumbers(input: string): {
  valid: string[];
  invalid: string[];
  duplicates: string[];
} {
  if (!input || typeof input !== 'string') {
    return { valid: [], invalid: [], duplicates: [] };
  }

  // Split by newlines, commas, semicolons, or multiple spaces
  const rawNumbers = input
    .split(/[\n,;]+|\s{2,}/)
    .map(num => num.trim())
    .filter(num => num.length > 0);

  const valid: string[] = [];
  const invalid: string[] = [];
  const duplicates: string[] = [];
  const seen = new Set<string>();

  for (const rawNumber of rawNumbers) {
    const cleaned = rawNumber.toUpperCase();
    
    if (seen.has(cleaned)) {
      duplicates.push(cleaned);
      continue;
    }
    
    seen.add(cleaned);
    
    if (validateTrackingNumber(cleaned)) {
      valid.push(cleaned);
    } else {
      invalid.push(rawNumber); // Keep original format for error display
    }
  }

  return { valid, invalid, duplicates };
}

/**
 * Validate tracking form input and return errors
 */
export function validateTrackingForm(
  input: string,
  maxNumbers: number = 20
): ValidationError[] {
  const errors: ValidationError[] = [];

  if (!input || input.trim().length === 0) {
    errors.push({
      field: 'input',
      message: 'Please enter at least one tracking number',
      code: 'REQUIRED',
    });
    return errors;
  }

  const { valid, invalid } = parseTrackingNumbers(input);

  if (valid.length === 0 && invalid.length > 0) {
    errors.push({
      field: 'input',
      message: 'No valid tracking numbers found',
      code: 'NO_VALID_NUMBERS',
    });
  }

  if (valid.length > maxNumbers) {
    errors.push({
      field: 'input',
      message: `Maximum ${maxNumbers} tracking numbers allowed`,
      code: 'TOO_MANY_NUMBERS',
    });
  }

  if (invalid.length > 0) {
    errors.push({
      field: 'input',
      message: `Invalid tracking numbers: ${invalid.slice(0, 3).join(', ')}${invalid.length > 3 ? '...' : ''}`,
      code: 'INVALID_FORMAT',
    });
  }

  return errors;
}

/**
 * Remove duplicates from tracking numbers array
 */
export function removeDuplicates(trackingNumbers: string[]): string[] {
  return Array.from(new Set(trackingNumbers.map(num => num.toUpperCase())));
}

/**
 * Format tracking number for display
 */
export function formatTrackingNumber(trackingNumber: string): string {
  if (!trackingNumber) return '';
  
  const cleaned = trackingNumber.trim().toUpperCase();
  
  // Add spacing for better readability
  if (THAI_POST_PATTERNS[0].test(cleaned)) {
    // Format: AB 123456789 TH
    return cleaned.replace(/^([A-Z]{2})(\d{9})(TH)$/, '$1 $2 $3');
  }
  
  if (cleaned.length >= 10 && /^\d+$/.test(cleaned)) {
    // Format numeric tracking numbers with spaces every 4 digits
    return cleaned.replace(/(\d{4})/g, '$1 ').trim();
  }
  
  return cleaned;
}

/**
 * Debounce function for input validation
 */
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait: number
): (...args: Parameters<T>) => void {
  let timeout: NodeJS.Timeout;
  
  return (...args: Parameters<T>) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  };
}