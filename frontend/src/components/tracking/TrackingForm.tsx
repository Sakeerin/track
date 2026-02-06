import React, { useState, useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { 
  parseTrackingNumbers, 
  validateTrackingForm, 
  debounce,
  formatTrackingNumber 
} from '../../utils/validation';
import { TrackingFormState, ValidationError } from '../../types/tracking.types';

interface TrackingFormProps {
  onSubmit: (trackingNumbers: string[]) => void;
  isLoading?: boolean;
  maxNumbers?: number;
  className?: string;
}

const TrackingForm: React.FC<TrackingFormProps> = ({
  onSubmit,
  isLoading = false,
  maxNumbers = 20,
  className = '',
}) => {
  const { t } = useTranslation();
  
  const [formState, setFormState] = useState<TrackingFormState>({
    input: '',
    validatedNumbers: [],
    errors: [],
    isValidating: false,
    isSubmitting: false,
  });

  // Debounced validation function
  const debouncedValidate = useCallback(
    debounce((input: string) => {
      setFormState(prev => ({ ...prev, isValidating: true }));
      
      const errors = validateTrackingForm(input, maxNumbers);
      const { valid } = parseTrackingNumbers(input);
      
      setFormState(prev => ({
        ...prev,
        validatedNumbers: valid,
        errors,
        isValidating: false,
      }));
    }, 300),
    [maxNumbers]
  );

  // Handle input change
  const handleInputChange = (event: React.ChangeEvent<HTMLTextAreaElement>) => {
    const input = event.target.value;
    
    setFormState(prev => ({
      ...prev,
      input,
      isValidating: true,
    }));

    if (input.trim()) {
      debouncedValidate(input);
    } else {
      setFormState(prev => ({
        ...prev,
        validatedNumbers: [],
        errors: [],
        isValidating: false,
      }));
    }
  };

  // Handle form submission
  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    
    if (isLoading || formState.isSubmitting) return;
    
    const errors = validateTrackingForm(formState.input, maxNumbers);
    
    if (errors.length > 0) {
      setFormState(prev => ({ ...prev, errors }));
      return;
    }

    const { valid } = parseTrackingNumbers(formState.input);
    
    if (valid.length === 0) {
      setFormState(prev => ({
        ...prev,
        errors: [{
          field: 'input',
          message: t('tracking.form.noValidNumbers', 'No valid tracking numbers found'),
          code: 'NO_VALID_NUMBERS',
        }],
      }));
      return;
    }

    setFormState(prev => ({ ...prev, isSubmitting: true }));
    onSubmit(valid);
  };

  // Reset submitting state when loading changes
  useEffect(() => {
    if (!isLoading) {
      setFormState(prev => ({ ...prev, isSubmitting: false }));
    }
  }, [isLoading]);

  // Handle paste event to clean up pasted content
  const handlePaste = (event: React.ClipboardEvent<HTMLTextAreaElement>) => {
    event.preventDefault();
    const pastedText = event.clipboardData.getData('text');
    
    // Clean up pasted content (remove extra whitespace, normalize line breaks)
    const cleanedText = pastedText
      .replace(/\r\n/g, '\n')
      .replace(/\r/g, '\n')
      .replace(/\s+/g, ' ')
      .replace(/\n\s+/g, '\n')
      .trim();
    
    const currentInput = formState.input;
    const textarea = event.currentTarget;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    
    const newInput = currentInput.substring(0, start) + cleanedText + currentInput.substring(end);
    
    setFormState(prev => ({ ...prev, input: newInput, isValidating: true }));
    
    if (newInput.trim()) {
      debouncedValidate(newInput);
    }
  };

  const hasErrors = formState.errors.length > 0;
  const hasValidNumbers = formState.validatedNumbers.length > 0;
  const isProcessing = isLoading || formState.isSubmitting || formState.isValidating;

  return (
    <div className={`tracking-form ${className}`}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label 
            htmlFor="tracking-input" 
            className="block text-sm font-medium text-gray-700 mb-2"
          >
            {t('tracking.form.label', 'Tracking Numbers')}
            <span className="text-gray-500 ml-1">
              ({t('tracking.form.maxNumbers', { count: maxNumbers })})
            </span>
          </label>
          
          <div className="relative">
            <textarea
              id="tracking-input"
              value={formState.input}
              onChange={handleInputChange}
              onPaste={handlePaste}
              placeholder={t('tracking.form.placeholder', 'Enter tracking numbers (one per line or separated by commas)')}
              className={`
                w-full px-3 py-2 border rounded-md resize-none
                focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                ${hasErrors ? 'border-red-300 bg-red-50' : 'border-gray-300'}
                ${isProcessing ? 'opacity-75' : ''}
              `}
              rows={4}
              disabled={isProcessing}
              aria-describedby={hasErrors ? 'tracking-errors' : undefined}
              aria-invalid={hasErrors}
            />
            
            {formState.isValidating && (
              <div className="absolute top-2 right-2">
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div>
              </div>
            )}
          </div>
          
          {/* Validation feedback */}
          {hasValidNumbers && !hasErrors && (
            <div className="mt-2 text-sm text-green-600">
              {t('tracking.form.validNumbers', {
                count: formState.validatedNumbers.length,
              })}
            </div>
          )}
          
          {/* Error messages */}
          {hasErrors && (
            <div id="tracking-errors" className="mt-2 space-y-1">
              {formState.errors.map((error, index) => (
                <div key={index} className="text-sm text-red-600 flex items-start">
                  <svg 
                    className="w-4 h-4 mt-0.5 mr-1 flex-shrink-0" 
                    fill="currentColor" 
                    viewBox="0 0 20 20"
                  >
                    <path 
                      fillRule="evenodd" 
                      d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" 
                      clipRule="evenodd" 
                    />
                  </svg>
                  {error.message}
                </div>
              ))}
            </div>
          )}
          
          {/* Preview of validated numbers */}
          {hasValidNumbers && formState.validatedNumbers.length <= 5 && (
            <div className="mt-2">
              <div className="text-xs text-gray-500 mb-1">
                {t('tracking.form.preview', 'Preview:')}
              </div>
              <div className="flex flex-wrap gap-1">
                {formState.validatedNumbers.map((number, index) => (
                  <span 
                    key={index}
                    className="inline-block px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded"
                  >
                    {formatTrackingNumber(number)}
                  </span>
                ))}
              </div>
            </div>
          )}
        </div>
        
        <button
          type="submit"
          disabled={isProcessing || hasErrors || !hasValidNumbers}
          className={`
            w-full py-2 px-4 rounded-md font-medium transition-colors duration-200
            focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
            ${
              isProcessing || hasErrors || !hasValidNumbers
                ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                : 'bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800'
            }
          `}
          aria-describedby="submit-button-status"
        >
          {isProcessing ? (
            <div className="flex items-center justify-center">
              <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
              {t('tracking.form.processing', 'Processing...')}
            </div>
          ) : (
            t('tracking.form.submit', 'Track Parcels')
          )}
        </button>
        
        <div id="submit-button-status" className="sr-only">
          {isProcessing && t('tracking.form.processing', 'Processing...')}
          {hasErrors && t('tracking.form.hasErrors', 'Please fix errors before submitting')}
          {!hasValidNumbers && t('tracking.form.noNumbers', 'Please enter valid tracking numbers')}
        </div>
      </form>
    </div>
  );
};

export default TrackingForm;