import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { I18nextProvider } from 'react-i18next';
import i18n from '../../../i18n/config';
import TrackingForm from '../TrackingForm';

// Mock the debounce function to make tests synchronous
jest.mock('../../../utils/validation', () => ({
  ...jest.requireActual('../../../utils/validation'),
  debounce: (fn: any) => fn,
}));

const renderWithI18n = (component: React.ReactElement) => {
  return render(
    <I18nextProvider i18n={i18n}>
      {component}
    </I18nextProvider>
  );
};

describe('TrackingForm', () => {
  const mockOnSubmit = jest.fn();

  beforeEach(() => {
    mockOnSubmit.mockClear();
  });

  it('renders form with correct elements', () => {
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} />
    );

    expect(screen.getByLabelText(/tracking numbers/i)).toBeInTheDocument();
    expect(screen.getByPlaceholderText(/enter tracking numbers/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /track parcels/i })).toBeInTheDocument();
  });

  it('validates tracking numbers on input', async () => {
    const user = userEvent.setup();
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} />
    );

    const textarea = screen.getByLabelText(/tracking numbers/i);
    
    // Enter valid tracking number
    await act(async () => {
      await user.type(textarea, 'TH1234567890');
    });
    
    await waitFor(() => {
      expect(screen.getByText(/1 valid tracking number found/i)).toBeInTheDocument();
    });
  });

  it('shows error for invalid tracking numbers', async () => {
    const user = userEvent.setup();
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} />
    );

    const textarea = screen.getByLabelText(/tracking numbers/i);
    
    // Enter invalid tracking number
    await act(async () => {
      await user.type(textarea, 'invalid123');
    });
    
    await waitFor(() => {
      expect(screen.getByText(/invalid tracking numbers/i)).toBeInTheDocument();
    });
  });

  it('handles multiple tracking numbers', async () => {
    const user = userEvent.setup();
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} />
    );

    const textarea = screen.getByLabelText(/tracking numbers/i);
    
    // Enter multiple valid tracking numbers
    await act(async () => {
      await user.type(textarea, 'TH1234567890\nAB123456789TH\nCD987654321');
    });
    
    await waitFor(() => {
      expect(screen.getByText(/3 valid tracking number found/i)).toBeInTheDocument();
    });
  });

  it('enforces maximum number limit', async () => {
    const user = userEvent.setup();
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} maxNumbers={2} />
    );

    const textarea = screen.getByLabelText(/tracking numbers/i);
    
    // Enter more than max allowed
    await act(async () => {
      await user.type(textarea, 'TH1234567890\nAB123456789TH\nCD987654321');
    });
    
    await waitFor(() => {
      expect(screen.getByText(/maximum 2 tracking numbers allowed/i)).toBeInTheDocument();
    });
  });

  it('removes duplicates', async () => {
    const user = userEvent.setup();
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} />
    );

    const textarea = screen.getByLabelText(/tracking numbers/i);
    
    // Enter duplicate tracking numbers
    await act(async () => {
      await user.type(textarea, 'TH1234567890\nTH1234567890\nAB123456789TH');
    });
    
    await waitFor(() => {
      expect(screen.getByText(/2 valid tracking number found/i)).toBeInTheDocument();
    });
  });

  it('submits valid tracking numbers', async () => {
    const user = userEvent.setup();
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} />
    );

    const textarea = screen.getByLabelText(/tracking numbers/i);
    const submitButton = screen.getByRole('button', { name: /track parcels/i });
    
    // Enter valid tracking numbers
    await act(async () => {
      await user.type(textarea, 'TH1234567890\nAB123456789TH');
    });
    
    await waitFor(() => {
      expect(screen.getByText(/2 valid tracking number found/i)).toBeInTheDocument();
    });

    // Submit form
    await act(async () => {
      await user.click(submitButton);
    });
    
    expect(mockOnSubmit).toHaveBeenCalledWith(['TH1234567890', 'AB123456789TH']);
  });

  it('prevents submission with invalid numbers', async () => {
    const user = userEvent.setup();
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} />
    );

    const textarea = screen.getByLabelText(/tracking numbers/i);
    const submitButton = screen.getByRole('button', { name: /track parcels/i });
    
    // Enter invalid tracking numbers
    await act(async () => {
      await user.type(textarea, 'invalid123');
    });
    
    await waitFor(() => {
      expect(screen.getByText(/invalid tracking numbers/i)).toBeInTheDocument();
    });

    // Try to submit form
    await act(async () => {
      await user.click(submitButton);
    });
    
    expect(mockOnSubmit).not.toHaveBeenCalled();
  });

  it('shows loading state', () => {
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} isLoading={true} />
    );

    const submitButton = screen.getByRole('button', { name: /processing/i });
    expect(submitButton).toBeDisabled();
  });

  it('handles paste events', async () => {
    const user = userEvent.setup();
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} />
    );

    const textarea = screen.getByLabelText(/tracking numbers/i);
    
    // Simulate paste event by directly setting value and triggering change
    await act(async () => {
      await user.click(textarea);
      await user.clear(textarea);
      await user.type(textarea, 'TH1234567890, AB123456789TH, CD987654321');
    });
    
    await waitFor(() => {
      expect(screen.getByText(/3 valid tracking number found/i)).toBeInTheDocument();
    });
  });

  it('shows preview for small number of tracking numbers', async () => {
    const user = userEvent.setup();
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} />
    );

    const textarea = screen.getByLabelText(/tracking numbers/i);
    
    // Enter a few tracking numbers
    await act(async () => {
      await user.type(textarea, 'TH1234567890\nAB123456789TH');
    });
    
    await waitFor(() => {
      expect(screen.getByText(/preview:/i)).toBeInTheDocument();
      expect(screen.getByText('TH1234567890')).toBeInTheDocument();
    });
  });

  it('has proper accessibility attributes', () => {
    renderWithI18n(
      <TrackingForm onSubmit={mockOnSubmit} />
    );

    const textarea = screen.getByLabelText(/tracking numbers/i);
    const submitButton = screen.getByRole('button', { name: /track parcels/i });

    expect(textarea).toHaveAttribute('aria-invalid', 'false');
    expect(submitButton).toHaveAttribute('aria-describedby', 'submit-button-status');
  });
});