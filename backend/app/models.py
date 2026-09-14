import datetime
from sqlalchemy import Column, Integer, String, Text, Float, DateTime
from .database import Base

class Lead(Base):
    __tablename__ = "leads"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    email = Column(String(255), nullable=False, index=True)
    phone = Column(String(100), nullable=True)
    brochure_name = Column(String(255), nullable=True)
    message = Column(Text, nullable=True)
    source = Column(String(100), default="website")
    created_at = Column(DateTime, default=datetime.datetime.utcnow)

class Application(Base):
    __tablename__ = "applications"

    id = Column(Integer, primary_key=True, index=True)
    application_number = Column(String(50), unique=True, index=True, nullable=False)
    full_name = Column(String(255), nullable=False)
    email = Column(String(255), nullable=False, index=True)
    phone = Column(String(100), nullable=True)
    course_slug = Column(String(255), nullable=False)
    location_slug = Column(String(255), nullable=False)
    batch_date = Column(String(100), nullable=True)
    room_type = Column(String(100), default="Double Sharing")
    payment_type = Column(String(50), default="deposit")
    amount = Column(Float, default=500.0)
    status = Column(String(50), default="pending")
    created_at = Column(DateTime, default=datetime.datetime.utcnow)

class Course(Base):
    __tablename__ = "courses"

    id = Column(Integer, primary_key=True, index=True)
    title = Column(String(255), nullable=False)
    slug = Column(String(255), unique=True, index=True, nullable=False)
    duration_hours = Column(Integer, nullable=False)
    location = Column(String(255), nullable=False)
    description = Column(Text, nullable=True)
    price_double = Column(Float, default=2200.0)
    price_private = Column(Float, default=3200.0)
