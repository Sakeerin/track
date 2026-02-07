import { 
  RateLimitError, 
  ServiceUnavailableError, 
  ServerError, 
  TimeoutError, 
  NetworkError 
} from '../trackingApi';

describe('Custom Error Classes', () => {
  it('should create RateLimitError with correct properties', () => {
    const error = new RateLimitError('Rate limit exceeded');
    expect(error).toBeInstanceOf(Error);
    expect(error.name).toBe('RateLimitError');
    expect(error.message).toBe('Rate limit exceeded');
  });

  it('should create ServiceUnavailableError with correct properties', () => {
    const error = new ServiceUnavailableError('Service unavailable');
    expect(error).toBeInstanceOf(Error);
    expect(error.name).toBe('ServiceUnavailableError');
    expect(error.message).toBe('Service unavailable');
  });

  it('should create ServerError with correct properties', () => {
    const error = new ServerError('Server error');
    expect(error).toBeInstanceOf(Error);
    expect(error.name).toBe('ServerError');
    expect(error.message).toBe('Server error');
  });

  it('should create TimeoutError with correct properties', () => {
    const error = new TimeoutError('Timeout');
    expect(error).toBeInstanceOf(Error);
    expect(error.name).toBe('TimeoutError');
    expect(error.message).toBe('Timeout');
  });

  it('should create NetworkError with correct properties', () => {
    const error = new NetworkError('Network error');
    expect(error).toBeInstanceOf(Error);
    expect(error.name).toBe('NetworkError');
    expect(error.message).toBe('Network error');
  });

  it('should inherit from Error for proper stack traces', () => {
    const rateLimitError = new RateLimitError('test');
    const serverError = new ServerError('test');
    const timeoutError = new TimeoutError('test');
    const networkError = new NetworkError('test');
    const serviceError = new ServiceUnavailableError('test');

    expect(rateLimitError.stack).toBeDefined();
    expect(serverError.stack).toBeDefined();
    expect(timeoutError.stack).toBeDefined();
    expect(networkError.stack).toBeDefined();
    expect(serviceError.stack).toBeDefined();
  });

  it('should be catchable with instanceof', () => {
    try {
      throw new RateLimitError('test');
    } catch (e) {
      expect(e instanceof RateLimitError).toBe(true);
      expect(e instanceof Error).toBe(true);
    }
  });
});
