-- Create Patient table
CREATE TABLE IF NOT EXISTS patient (
    id SERIAL PRIMARY KEY,
    fname VARCHAR(120) NOT NULL,
    lname VARCHAR(120) NOT NULL,
    dob DATE,
    sex VARCHAR(50)
);

-- Create Acquisition table
CREATE TABLE IF NOT EXISTS acquisition (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER REFERENCES patient(id) ON DELETE CASCADE,
    eye VARCHAR(50) NOT NULL,
    site VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    operator VARCHAR(50) NOT NULL
);

-- Optional: Add indexes for faster lookups if needed
-- CREATE INDEX IF NOT EXISTS idx_patient_name ON patient (fname, lname);
-- CREATE INDEX IF NOT EXISTS idx_acquisition_patient_id ON acquisition (patient_id);